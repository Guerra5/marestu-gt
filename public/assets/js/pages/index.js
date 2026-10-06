(() => {
'use strict';
const pageData = JSON.parse(document.getElementById('index-data').textContent);

  const estadoLabels = pageData.value0;
  const estadoValues = pageData.value1;

  const topLabels = pageData.value2;
  const topValues = pageData.value3;

  const ctx1 = document.getElementById('chartEstados');
  new Chart(ctx1, {
    type: 'doughnut',
    data: {
      labels: estadoLabels,
      datasets: [{ data: estadoValues }]
    },
    options: {
      plugins: { legend: { position: 'bottom' } }
    }
  });

  const ctx2 = document.getElementById('chartTop');
  new Chart(ctx2, {
    type: 'bar',
    data: {
      labels: topLabels,
      datasets: [{ data: topValues }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
})();
