$(document).ready(function(){
    var url = OC.generateUrl('apps/'+DWEmbed.appName+'/refresh');
    setInterval(function(){
        $.post(url);
    }, DWEmbed.refreshInterval*1000);
});
