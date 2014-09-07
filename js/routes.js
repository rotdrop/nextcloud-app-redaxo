$(document).ready(function(){
    var url = OC.generateUrl('apps/'+Redaxo.appName+'/refresh');
    setInterval(function(){
        $.post(url);
    }, Redaxo.refreshInterval*1000);
});
