$(document).ready(function(){
    var url = OC.generateUrl('apps/dokuwikiembed/refresh');
    setInterval(function(){
        $.post(url);
    }, 300000);
});
