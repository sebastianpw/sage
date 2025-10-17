let currentPage = 1;
let rowsPerPage = 10;
let initialSearch = $('#searchInput').val();

function loadTable(sort='', order='', search='', page=1) {
    let url = 'sql_crud_scheduled_tasks.php';
    if(sort && order) url += '?sort=' + sort + '&order=' + order;

    $.post(url, {action:'fetch', search:search, page:page, limit:rowsPerPage}, function(data){
        data = JSON.parse(data);
        let rows = '';
        data.rows.forEach(function(row){
            rows += `<tr data-id="${row.id}">`;

            // Run now button
            rows += `<td data-label="Run"><button class="runBtn">Run Now</button></td>`;

            // Action buttons
            rows += `<td data-label="Action"><button class="copyBtn">Copy</button> <button class="deleteBtn">Delete</button></td>`;

            // Visible fields
            rows += `<td data-label="id">${row['id']}</td>`;
            rows += `<td contenteditable='true' data-label="name" data-field="name">${row['name'] ?? ''}</td>`;
            rows += `<td contenteditable='true' data-label="args" data-field="args">${row['args'] ?? ''}</td>`;
            rows += `<td contenteditable='true' data-label="script_path" data-field="script_path">${row['script_path'] ?? ''}</td>`;
            rows += `<td data-label="last_run">${row['last_run'] ?? ''}</td>`;

            rows += '</tr>';
        });
        $('#scheduled_tasksTable tbody').html(rows);

        let paginationHtml = '';
        for (let i=1; i<=data.totalPages; i++) {
            paginationHtml += `<button class="pageBtn ${i==data.currentPage?'active':''}" data-page="${i}">${i}</button>`;
        }
        $('#pagination').html(paginationHtml);
        currentPage = data.currentPage;
    });
}

$(document).ready(function(){
    loadTable(sort, order, initialSearch, currentPage);

    $('#searchBtn').click(function(){
        let search = $('#searchInput').val();
        loadTable(sort, order, search, 1);
    });

    $('#searchInput').on('keyup', function(e){
        if(e.key === 'Enter') {
            let search = $(this).val();
            loadTable(sort, order, search, 1);
        }
    });

    $(document).on('blur', 'td[contenteditable="true"]', function(){
        let td = $(this);
        let value = td.text();
        let field = td.data('field');
        let id = td.closest('tr').data('id');

        $.post('sql_crud_scheduled_tasks.php', {action:'update', id:id, field:field, value:value}, function(res){
            if(res != 'success') alert('Update failed');
        });
    });

    $(document).on('click', '.deleteBtn', function(){
        if(!confirm('Are you sure?')) return;
        let id = $(this).closest('tr').data('id');
        $.post('sql_crud_scheduled_tasks.php', {action:'delete', id:id}, function(res){
            if(res=='success') loadTable(sort, order, $('#searchInput').val(), currentPage);
        });
    });

    $(document).on('click', '#addBtn', function(){
        $.post('sql_crud_scheduled_tasks.php', {action:'add'}, function(id){
            loadTable(sort, order, $('#searchInput').val(), currentPage);
        });
    });

    $(document).on('click', '.copyBtn', function(){
        let id = $(this).closest('tr').data('id');
        $.post('sql_crud_scheduled_tasks.php', {action:'copy', id:id}, function(newId){
            loadTable(sort, order, $('#searchInput').val(), currentPage);
        });
    });

    $(document).on('click', '.runBtn', function(){
        let id = $(this).closest('tr').data('id');
        $.post('sql_crud_scheduled_tasks.php', {action:'run', id:id}, function(res){
            let data = JSON.parse(res);
            if(data.status === 'success') {
                alert('Task executed!');
                loadTable(sort, order, $('#searchInput').val(), currentPage);
            } else {
                alert(data.message || 'Run failed');
            }
        });
    });

    $(document).on('click', '.sortHeader', function(e){
        e.preventDefault();
        sort = $(this).data('column');
        order = $(this).data('order');
        let search = $('#searchInput').val();
        loadTable(sort, order, search, 1);
    });

    $(document).on('click', '.pageBtn', function(){
        let page = $(this).data('page');
        let search = $('#searchInput').val();
        loadTable(sort, order, search, page);
    });
});


