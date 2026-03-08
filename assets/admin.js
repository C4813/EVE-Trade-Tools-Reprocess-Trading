(function($){
    function setStatus(state, text){
        var $dot = $('#ett-rt-db-dot');
        var $txt = $('#ett-rt-db-status-text');

        $dot.removeClass('ok bad');
        $txt.removeClass('ett-ok ett-bad ett-muted');

        if (state === 'ok'){
            $dot.addClass('ok');
            $txt.addClass('ett-ok').text(text || 'Connected');
        } else if (state === 'bad'){
            $dot.addClass('bad');
            $txt.addClass('ett-bad').text(text || 'Not connected');
        } else {
            $txt.addClass('ett-muted').text(text || 'Not tested.');
        }
    }

    function testConnection(){
        setStatus(null, 'Testing...');

        return $.post(ETT_RT_Admin.ajaxUrl, {
            action: 'ett_rt_test_extdb',
            nonce: ETT_RT_Admin.nonce
        }).done(function(resp){
            if (resp && resp.ok){
                setStatus('ok', resp.message || 'Connected');
            } else {
                setStatus('bad', (resp && resp.message) ? resp.message : 'Not connected');
            }
        }).fail(function(xhr){
            var msg = 'Not connected';
            if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            setStatus('bad', msg);
        });
    }

    $(function(){
        // auto-test on load if configured
        if (ETT_RT_Admin && ETT_RT_Admin.configured){
            testConnection();
        } else {
            setStatus('bad', 'Not configured');
        }
    });
})(jQuery);
