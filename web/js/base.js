$(function () {
    $('.check-user-permission').change(function() {
        let username = $(this).text();
        let filename = $(this).parent().parent().parent().find('td:first > div').text();
        let checked = $(this).find('input').is(':checked');
        $.post('/check_user_permission', { 'username': username, 'filename': filename, 'checked': checked }, 'json');
    });
});