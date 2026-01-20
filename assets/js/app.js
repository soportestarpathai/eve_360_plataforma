document.addEventListener('DOMContentLoaded', function() {
    
    const loadBtn = document.getElementById('loadClientsBtn');
    const tableBody = document.querySelector('#clientsTable tbody');

    loadBtn.addEventListener('click', function() {
        fetchClients();
    });

    function fetchClients() {
        // Show a loading state
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center">Cargando datos...</td></tr>';

        // AJAX Request to the PHP API
        fetch('api/get_clients.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(json => {
                if(json.status === 'success') {
                    renderTable(json.data);
                } else {
                    alert('Error en el servidor: ' + json.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                tableBody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Error al cargar datos</td></tr>';
            });
    }

    function renderTable(data) {
        tableBody.innerHTML = ''; // Clear loading message

        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center">No se encontraron clientes.</td></tr>';
            return;
        }

        data.forEach(client => {
            const row = `
                <tr>
                    <td>${client.id_cliente}</td>
                    <td>${client.no_contrato}</td>
                    <td><strong>${client.nombre_cliente}</strong></td>
                    <td>${client.rfc}</td>
                    <td>${client.fecha_apertura}</td>
                </tr>
            `;
            tableBody.innerHTML += row;
        });
    }
});