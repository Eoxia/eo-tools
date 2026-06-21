jQuery(document).ready(function($) {
	if (typeof window.eoCookieChartData === 'undefined') return;

	const ctx = document.getElementById('eoCookieStatsChart');
	if (ctx) {
		new Chart(ctx, {
			type: 'line',
			data: {
				labels: window.eoCookieChartData.labels,
				datasets: [
					{
						label: 'Requêtes (Vues)',
						data: window.eoCookieChartData.views,
						borderColor: '#94a3b8',
						backgroundColor: 'rgba(148, 163, 184, 0.2)',
						yAxisID: 'y',
						fill: true,
						tension: 0.1
					},
					{
						label: '% de consentement',
						data: window.eoCookieChartData.consent,
						borderColor: '#4b5563',
						backgroundColor: '#4b5563',
						type: 'bar',
						yAxisID: 'y1'
					}
				]
			},
			options: {
				responsive: true,
				interaction: {
					mode: 'index',
					intersect: false,
				},
				scales: {
					y: {
						type: 'linear',
						display: true,
						position: 'left',
						title: {
							display: true,
							text: 'Requêtes'
						},
						min: 0
					},
					y1: {
						type: 'linear',
						display: true,
						position: 'right',
						title: {
							display: true,
							text: '% de consentement'
						},
						min: 0,
						max: 100,
						grid: {
							drawOnChartArea: false,
						},
					},
				}
			}
		});
	}

	$('#eoCookieExportCsv').on('click', function(e) {
		e.preventDefault();
		const data = window.eoCookieChartData;
		let csvContent = "data:text/csv;charset=utf-8,";
		csvContent += "Date,Requetes,% Consentement\n";
		
		for (let i = 0; i < data.labels.length; i++) {
			csvContent += data.labels[i] + "," + data.views[i] + "," + data.consent[i] + "\n";
		}

		const encodedUri = encodeURI(csvContent);
		const link = document.createElement("a");
		link.setAttribute("href", encodedUri);
		link.setAttribute("download", "eo_tools_cookie_stats.csv");
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
	});
});
