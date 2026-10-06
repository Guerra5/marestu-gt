'use strict';
// Chrome DevTools QA, using a dedicated profile and the isolated QA database.
const fs = require('node:fs');
const path = require('node:path');
const dir = fs.readFileSync(path.join(__dirname, '../backups/qa/latest.txt'), 'utf8').trim();
const meta = JSON.parse(fs.readFileSync(path.join(dir, 'browser.json'), 'utf8'));
if (!/^marestu_qa_\d{8}_\d{6}_[a-f0-9]{6}$/.test(meta.database) || meta.base !== 'http://127.0.0.1:18081/') throw Error('Not a QA target.');
const results = [], errors = [], network = [], dialogs = [];
let ws, seq = 0, autoDialog = true;
const pending = new Map();
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const literal = JSON.stringify;
function check(ok, label, evidence) {
  results.push({ passed: !!ok, case: label, evidence });
  console.log((ok ? 'PASS ' : 'FAIL ') + label + (ok || evidence === undefined ? '' : ' ' + literal(evidence)));
}
function send(method, params = {}) {
  return new Promise((resolve, reject) => {
    const id = ++seq;
    const timer = setTimeout(() => { pending.delete(id); reject(Error('CDP timeout: ' + method)); }, 20000);
    pending.set(id, { resolve, reject, timer });
    ws.send(literal({ id, method, params }));
  });
}
async function evaluate(expression) {
  const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  if (result.exceptionDetails) throw Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
  return result.result.value;
}
async function until(expression) {
  for (let i = 0; i < 100; i++) { try { if (await evaluate(expression)) return; } catch {} await delay(100); }
  throw Error('Browser condition timeout: ' + expression);
}
async function navigate(page) {
  await send('Page.navigate', { url: meta.base + page });
  const target = page === 'logout.php' ? 'login.php' : page;
  await until('location.href.startsWith(' + literal(meta.base + target) + ') && document.readyState === ' + literal('complete'));
  await delay(300);
}
async function fill(selector, value) {
  return evaluate('(() => { const e=document.querySelector(' + literal(selector) + '); if(!e) throw Error(' + literal('Missing control: ' + selector) + '); e.value=' + literal(String(value)) + '; e.dispatchEvent(new Event(' + literal('input') + ',{bubbles:true})); e.dispatchEvent(new Event(' + literal('change') + ',{bubbles:true})); return e.value; })()');
}
async function click(selector) { await evaluate('document.querySelector(' + literal(selector) + ').click()'); }
async function submit(selector) {
  await evaluate('document.querySelector(' + literal(selector) + ').requestSubmit()');
  await delay(700);
  await until('document.readyState === ' + literal('complete'));
}
async function screenshot(name) {
  const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
  fs.writeFileSync(path.join(dir, name + '.png'), Buffer.from(shot.data, 'base64'));
}
async function login(username) {
  await navigate('login.php');
  await fill('[name=usuario]', username); await fill('[name=password]', meta.password);
  await submit('form');
  await until('location.pathname.endsWith(' + literal('/index.php') + ')');
}
async function main() {
  const targets = await (await fetch('http://127.0.0.1:19222/json')).json();
  ws = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; });
  ws.onmessage = event => {
    const message = JSON.parse(event.data);
    if (message.id) {
      const task = pending.get(message.id); if (!task) return;
      clearTimeout(task.timer); pending.delete(message.id);
      message.error ? task.reject(Error(literal(message.error))) : task.resolve(message.result);
    } else if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails);
    else if (message.method === 'Network.loadingFailed') network.push(message.params);
    else if (message.method === 'Network.responseReceived' && message.params.response.status >= 400) network.push({ url: message.params.response.url, status: message.params.response.status });
    else if (message.method === 'Page.javascriptDialogOpening') {
      dialogs.push(message.params);
      send('Page.handleJavaScriptDialog', { accept: autoDialog }).catch(e => errors.push({ text: e.message }));
    }
  };
  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  await send('Network.clearBrowserCookies');
  await send('Emulation.setDeviceMetricsOverride', { width:1440,height:1000,deviceScaleFactor:1,mobile:false });
  await navigate('login.php'); await click('#showPass');
  check(await evaluate('document.querySelector(' + literal('#password') + ').type===' + literal('text')), 'Login show-password JavaScript works');
  await login(meta.username); check(true, 'Admin logs in using browser form');
  await screenshot('dashboard-desktop');
  for (const page of ['clientes.php','categorias.php','articulos.php','reservas.php','kardex.php','usuarios.php','calendario.php']) {
    await navigate(page);
    check(await evaluate('!!document.querySelector(' + literal('main') + ')'), 'Browser renders ' + page);
    if (page === 'calendario.php') check(await evaluate('!!document.querySelector(' + literal('.fc-view-harness') + ')'), 'FullCalendar initializes');
  }
  await navigate('reservas.php');
  const dates = [5,6,7].map(n => { const d=new Date(); d.setUTCDate(d.getUTCDate()+n); return d.toISOString().slice(0,10); });
  await evaluate('document.querySelector(' + literal('[name=cliente_id]') + ').selectedIndex=1');
  for (const [selector,value] of [['#fechaSalida',dates[0]],['#fechaEvento',dates[1]],['#fechaRetorno',dates[2]],['#direccionEvento','QA navegador: Salón Norte, puerta 2']]) await fill(selector,value);
  await submit('#formCrearReserva');
  await until('location.pathname.endsWith(' + literal('/reserva_editar.php') + ')');
  const id = await evaluate('new URL(location.href).searchParams.get(' + literal('id') + ')');
  check(!!id, 'Browser creates reservation with event address');
  await fill('#buscarArticuloReserva','NO_EXISTE_QA');
  check(await evaluate('document.querySelector(' + literal('#selectArticuloReserva') + ').options.length===1'), 'Article search filters nonmatching options');
  await fill('#buscarArticuloReserva','QA Mesa'); await fill('#selectArticuloReserva',meta.article);
  await fill('#formAgregarArticulo [name=cantidad]',3); await submit('#formAgregarArticulo');
  check(await evaluate('document.body.innerText.includes(' + literal('QA Mesa editada') + ')'), 'Browser adds article to draft');
  await screenshot('reservation-desktop');
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true}); await delay(300);
  const mobile = await evaluate('({width:innerWidth,scroll:document.documentElement.scrollWidth})');
  check(mobile.scroll <= mobile.width + 1,'Reservation page fits mobile viewport',mobile); await screenshot('reservation-mobile');
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
  autoDialog=false; await click('button[name=to][value=CONFIRMADA]'); await delay(300);
  check(dialogs.length>0 && await evaluate('document.body.innerText.includes(' + literal('BORRADOR') + ')'),'Cancel confirmation dialog preserves draft');
  autoDialog=true; await click('button[name=to][value=CONFIRMADA]'); await delay(600);
  check(await evaluate('!document.querySelector(' + literal('button[name=to][value=CONFIRMADA]') + ') && document.body.innerText.includes(' + literal('CONFIRMADA') + ')'),'Accept confirmation submits reservation');
  await navigate('reserva_operacion.php?id='+id); await click('.btnEditarPedido'); await delay(400);
  check(await evaluate('document.querySelector(' + literal('#modalEditarPedido') + ').classList.contains(' + literal('show') + ') && document.querySelector(' + literal('#editarNuevaCantidad') + ').value===' + literal('3')),'Edit-item modal opens and receives row data');
  await click('#modalEditarPedido [data-bs-dismiss=modal]'); await delay(350);
  await navigate('reserva_nota_entrega.php?id='+id); await send('Emulation.setEmulatedMedia',{media:'print'});
  check(await evaluate('document.body.innerText.includes(' + literal('QA navegador: Salón Norte, puerta 2') + ') && getComputedStyle(document.querySelector(' + literal('.no-print') + ')).display===' + literal('none')),'Print media keeps delivery address and hides controls');
  await screenshot('delivery-note-print');
  const pdf = await send('Page.printToPDF',{printBackground:true,preferCSSPageSize:true});
  fs.writeFileSync(path.join(dir,'delivery-note-browser.pdf'),Buffer.from(pdf.data,'base64'));
  check(Buffer.from(pdf.data,'base64').subarray(0,4).toString()==='%PDF','Browser prints delivery note to PDF (separate from Dompdf)');
  await send('Emulation.setEmulatedMedia',{media:''});
  await navigate('logout.php'); await login('qa_operador');
  await navigate('reserva_editar.php?id='+id);
  check(await evaluate('!document.querySelector(' + literal('textarea[name=direccion_evento]') + ') && !document.querySelector(' + literal('button[name=to]') + ')'),'Operator browser hides reservation administration');
  await navigate('reserva_operacion.php?id='+id);
  check(await evaluate('!document.querySelector(' + literal('.btnEditarPedido') + ')'),'Operator cannot edit confirmed order in browser');
  const deliveryName=await evaluate('document.querySelector(' + literal('input[name^=entrega_]') + ').name');
  await fill('[name='+deliveryName+']',3);
  await evaluate('document.querySelector(' + literal('[name='+deliveryName+']') + ').form.requestSubmit()'); await delay(700);
  check(await evaluate('!!document.querySelector(' + literal('input[name^=dev_]') + ')'),'Operator delivers through browser form');
  const returnName=await evaluate('document.querySelector(' + literal('input[name^=dev_]') + ').name');
  await fill('[name='+returnName+']',3);
  await evaluate('document.querySelector(' + literal('[name='+returnName+']') + ').form.requestSubmit()'); await delay(700);
  check(await evaluate('document.body.innerText.includes(' + literal('DEVUELTA') + ')'),'Operator completes return through browser form');
  check(errors.length===0,'No uncaught browser JavaScript errors',errors);
  const failures=network.filter(n=>n.url && !n.url.endsWith('/favicon.ico'));
  check(failures.length===0,'No failed application/CDN HTTP responses',failures);
}
main().catch(e=>check(false,'Browser QA could not continue',e.message)).finally(()=>{
  fs.writeFileSync(path.join(dir,'browser-results.json'),literal({results,errors,network,dialogs},null,2));
  console.log(literal({passed:results.filter(r=>r.passed).length,failed:results.filter(r=>!r.passed).length}));
  if(ws) ws.close(); process.exitCode=results.some(r=>!r.passed)?1:0;
});
