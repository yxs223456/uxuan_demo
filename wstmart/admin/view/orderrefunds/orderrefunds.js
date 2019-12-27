var grid;
$(function(){
    var laydate = layui.laydate;
    laydate.render({
        elem: '#startDate'
    });
    laydate.render({
        elem: '#endDate'
    });
})
function toView(id){
	location.href=WST.U('admin/orders/view','id='+id);
}

function initRefundGrid(){
    var h = WST.pageHeight();
    var cols = [
            {title:'订单编号', name:'orderNo',sortable: true, renderer: function(val,item,rowIndex){
            	var h = "";
	            if(item['orderSrc']==0){
	            	h += "<img class='order-source2' src='"+WST.conf.ROOT+"/wstmart/admin/view/img/order_source_1.png'>";	
	            }else if(item['orderSrc']==1){
	            	h += "<img class='order-source' src='"+WST.conf.ROOT+"/wstmart/admin/view/img/order_source_3.png'>";		
	            }else if(item['orderSrc']==2){
	            	h += "<img class='order-source' src='"+WST.conf.ROOT+"/wstmart/admin/view/img/order_source_2.png'>";		
	            }else if(item['orderSrc']==3){
	            	h += "<img class='order-source' src='"+WST.conf.ROOT+"/wstmart/admin/view/img/order_source_4.png'>";	
	            }else if(item['orderSrc']==4){
	            	h += "<img class='order-source' src='"+WST.conf.ROOT+"/wstmart/admin/view/img/order_source_5.png'>";	
	            }
                h += "<a style='cursor:pointer' onclick='javascript:showDetail("+ item['orderId'] +");'>"+item['orderNo']+"</a>";
	            return h;
            }},
            {title:'申请人', name:'loginName',sortable: true},
            {title:'店铺', name:'shopName',sortable: true},
            {title:'订单来源', name:'orderCodeTitle',width:40,sortable: true,hidden: true},
            {title:'配送方式', name:'deliverType',width:40,sortable: true,hidden: true},
            {title:'实收金额', name:'realTotalMoney', width:30,sortable: true,renderer: function(val,item,rowIndex){
            	return "¥"+val;
            }},
            {title:'申请退款金额', name:'backMoney',width:30,sortable: true, renderer: function(val,item,rowIndex){
                return "¥"+val;
            }},
            {title:'退还积分', name:'useScore',width:30,sortable: true},
            {title:'申请时间', name:'createTime',sortable: true},
            {title:'支付来源', name:'refundTo',sortable: true},
            {title:'退款状态', name:'isRefund', width:30,sortable: true,renderer: function(val,item,rowIndex){
            	return (item['isRefund']==1)?"已退款":"未退款";
            }},
            {title:'退款备注', name:'refundRemark',hidden: true},
            {title:'操作', name:'op' ,width:120, align:'center', renderer: function(val,item,rowIndex){
                var h = '';
	            if(item['isRefund']==0){
	            	if(WST.GRANT.TKDD_04)h += "<a class='btn btn-blue' href='javascript:toRefund(" + item['refundId'] + ")'><i class='fa fa-search'></i>退款</a> ";
	            }
	            h += "<a class='btn btn-blue' href='javascript:toView(" + item['orderId'] + ")'><i class='fa fa-search'></i>详情</a> ";
	            return h;
	        }}
            ];
 
    mmg = $('.mmg').mmGrid({height: (h-85),indexCol: true, indexColWidth:50, cols: cols,method:'POST',
        url: WST.U('admin/orderrefunds/refundPageQuery'), fullWidthRows: true, autoLoad: true,nowrap:true,
        remoteSort:true ,
        sortName: 'createTime',
        sortStatus: 'desc',
        plugins: [
            $('#pg').mmPaginator({})
        ]
    });  
}
function loadRefundGrid(){
	var p = WST.getParams('.j-ipt');
	p.page = 1;
	mmg.load(p);
}
var w;
function toRefund(id){
	var ll = WST.msg('正在加载信息，请稍候...');
	$.post(WST.U('admin/orderrefunds/toRefund',{id:id}),{},function(data){
		layer.close(ll);
		w =WST.open({type: 1,title:"订单退款",shade: [0.6, '#000'],offset:'50px',border: [0],content:data,area: ['550px', '380px']});
	});
}
function orderRefund(id){
	$('#editFrom').isValid(function(v){
		if(v){
        	var params = {};
        	params.content = $.trim($('#content').val());
        	params.id = id;
        	ll = WST.msg('正在处理数据，请稍候...');
		    $.post(WST.U('admin/orderrefunds/orderRefund'),params,function(data){
		    	layer.close(ll);
		    	var json = WST.toAdminJson(data);
				if(json.status==1){
                    layer.close(w);
					WST.msg(json.msg, {icon: 1,time:2500},function(){
                        loadRefundGrid();
                    });
				}else{
					WST.msg(json.msg, {icon: 2});
				}
		   });
		}
    })
}
function showDetail(id){
    parent.showBox({title:'订单详情',type:2,content:WST.U('admin/orders/view',{id:id,from:1}),area: ['1020px', '500px'],btn:['关闭']});
}
function toExport(){
	var params = {};
	params = WST.getParams('.j-ipt');
	var box = WST.confirm({content:"您确定要导出订单吗?",yes:function(){
		layer.close(box);
		location.href=WST.U('admin/orderrefunds/toExport',params);
    }});
}