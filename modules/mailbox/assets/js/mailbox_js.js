/**
 * Update email status 
 */
function update_field(group, action, value, mail_id, type='inbox'){
    var data = {};
    data.group = group;
    data.action = action;
    data.value = value;
    data.id = mail_id;
    data.type = type;     
    if(group == 'detail'){
        data.type = mailtype; 
    }
    $.post(admin_url + 'mailbox/update_field', data).done(function(response) {
        response = JSON.parse(response);
        if (response.success === true || response.success == 'true') {
            alert_float('success', response.message);            
            if(group == 'detail'){
                window.location.reload();
            } else {
                reload_mailbox_tables();
            }
            
        } else {
            alert_float('warning', response.message);
        }
    });
}

/**
 * Reload mailbox datagrid
 * @return 
 */
function reload_mailbox_tables() {
    var av_tasks_tables = ['.table-mailbox'];
    $.each(av_tasks_tables, function(i, selector) {
        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().ajax.reload(null, false);
        }
    });
}
function refreshMailboxLogs() {
    $.get(admin_url + 'mailbox/get_logs', function(response) {
        var tbody = $('#mailbox-logs');
        tbody.empty();
        response.forEach(function(log) {
            var date = new Date(log.date);
            tbody.append(
                '<tr>' +
                '<td>' + date.toLocaleString() + '</td>' +
                '<td>' + log.description + '</td>' +
                '</tr>'
            );
        });
    });
}

// Atualiza logs quando estiver na página de configurações
if ($('#mailbox-logs').length) {
    refreshMailboxLogs();
    setInterval(refreshMailboxLogs, 30000);
}
/**
 * Update multi-email 
 */
function update_mass(group, action, value, type = "inbox"){
    if(group == 'detail'){
        update_field(group, action, value, mailid, type);
    } else {
        if (confirm_delete()) {
            var table_mailbox = $('.table-mailbox');
            var rows = table_mailbox.find('tbody tr');
            var lstid = '';
            $.each(rows, function() {
                var checkbox = $($(this).find('td').eq(0)).find('input');
                if (checkbox.prop('checked') === true) {
                    lstid = lstid + checkbox.val() + ',';
                }
            });
            update_field(group, action, value, lstid, type);
        }
    }
}

/* function check_new_emails() {
    var refreshBtn = $('.fa-refresh');
    refreshBtn.addClass('fa-spin');
    
    $.get(admin_url + 'mailbox/check_emails_manually', function(response) {
        response = JSON.parse(response);
        if (response.success) {
            alert_float('success', response.message);
            reload_mailbox_tables();
        } else {
            alert_float('warning', response.message);
        }
        refreshBtn.removeClass('fa-spin');
    });
} */