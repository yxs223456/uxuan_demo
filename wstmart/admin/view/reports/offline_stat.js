var mmg;
function initSaleGrid(){
    var laydate = layui.laydate;
    laydate.render({
        elem: '#startDate'
    });
    laydate.render({
        elem: '#endDate'
    });
    laydate.render({
        elem: '#channel'
    });
    var h = WST.pageHeight();
    var cols = [
        {title:'渠道', name:'pid', width: 80},
        {title:'邀请用户数', name:'bindUserNum', width: 80},
        {title:'成功订单数', name:'successOrderNum', width: 80},
        {title:'未支付或待成团订单数', name:'unSuccessOrderNum', width: 150},
    ];

    mmg = $('.mmg').mmGrid({height: (h-85),indexCol: true,indexColWidth:50,  cols: cols,method:'POST',
        url: WST.U('admin/reports/offlineStatByPage',WST.getParams('.ipt')), fullWidthRows: true, autoLoad: true,
        plugins: [
            $('#pg').mmPaginator({})
        ]
    });
}
function loadGrid(){
    var params = WST.getParams('.ipt');
    params.page = 1;
    mmg.load(params);
}
function toolTip(){
    WST.toolTip();
}