$(function () {
    $('.check-user-permission').change(function() {
        let username = $(this).text();
        let filename = $(this).parent().parent().parent().find('td:first > div > a').text();
        let checked = $(this).find('input').is(':checked');
        $.post('/check_user_permission', { 'username': username, 'filename': filename, 'checked': checked }, 'json');
    });

    $('.update-local-storage').on('click', function() {
        $.get('/update_local_storage', function (data) {
            let user = $('.header-user').text();
            let key = data['key'];
            localStorage.setItem('kbrskey', key);
            let dataStorage = data['localstorage'];
            for (let i = 0; i < dataStorage.length; i++) {
                let name = dataStorage[i].name;
                let content = dataStorage[i].content;
                let encr = CryptoJS.AES.encrypt(content, key);
                localStorage.setItem('kbrs' + user + name, encr);
            }
        }, 'json');
    });

    $('.show-local').on('click', function() {
        let user = $('.header-user').text();
        let wantedFile = 'kbrs' + user + $(this).parent().find('a').text();
        let content = localStorage.getItem(wantedFile);
        if (content) {
            let key = localStorage.getItem('kbrskey');
            decr = CryptoJS.AES.decrypt(content, key);
            $(this).text(decr.toString(CryptoJS.enc.Utf8));
        } else {
            $(this).text('file not in local storage');
        }
    });
});