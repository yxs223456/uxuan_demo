var mmg;
$(function () {
    var h = WST.pageHeight();
    var cols = [
        // {title:'ID', name:'id', width:10},
        {title:'优惠券名称', name:'name', width:30, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['name']+"</p> </span>"
        }},
        {title:'店铺名称', name:'shopName', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['shopName']+"</p> </span>"
        }},
        {title:'面值', name:'couponValue', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['couponValue']+"元</p> </span>"
        }},
        {title:'兑换所需U币', name:'integral', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['integral']+"</p> </span>"
        }},
        {title:'使用条件', name:'useCondition', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['useCondition']+"</p> </span>"
        }},
        {title:'每日红包数量', name:'dailyLimitNum', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['dailyLimitNum']+"</p> </span>"
        }},
        // {title:'今日已兑换数量', name:'useCondition', width:10, renderer:function (val,item,rowIndex) {
        //     return "<span><p class='wst-nowrap'>"+item['useCondition']+"</p> </span>"
        // }},
        {title:'历史兑换总数', name:'receiveNum', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['receiveNum']+"</p> </span>"
        }},
        {title:'开始时间', name:'startDate', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['startDate']+"</p> </span>"
        }},
        {title:'结束时间', name:'endDate', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['endDate']+"</p> </span>"
        }},
        {title:'适用商品', name:'useObjects', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['useObjects']+"</p> </span>"
        }},
        {title:'操作', name:'op' ,width:50, align:'center', renderer: function(val,item,rowIndex){
            var h = "";
            if(WST.GRANT.DHHB_01)h += "<a class='btn btn-blue' href='javascript:exchangeEdit(" + item['couponId'] + ")' ><i class='fa fa-pencil'></i>修改</a> ";
            if(WST.GRANT.DHHB_01)h += "<a class='btn btn-red' href='javascript:toDel(" + item['couponId'] + ")'><i class='fa fa-trash-o'></i>删除</a> ";
            return h;
        }}
    ];
    mmg = $('.mmg').mmGrid({height: h-85,indexCol: true, cols: cols,method:'POST',
        url: WST.U('admin/coupons/exchangePageQuery'), fullWidthRows: true, autoLoad: true,
        plugins: [
            //$('#pg').mmPaginator({})
        ]
    });
    mmg.on('loadSuccess',function(data){
        layui.form.render();
        layui.form.on('switch(isShow1)', function(data){
            var id = $(this).attr("data");
            // if(this.checked){
            //     toggleIsShow(id, 1);
            // }else{
            //     toggleIsShow(id, 0);
            // }
        });
    })
})
function exchangeEdit(id){
    location.href = WST.U('admin/coupons/exchangeToEdit','couponId='+id);
}
function checkUseCondition(v){
    if(v==1){
        $('#useMoney').attr('disabled',false);
    }else{
        $('#useMoney').val(0);
        $('#useMoney').attr('disabled',true);
    }
}
function checkIsDailyLimit(v){
    if(v==1){
        $('#dailyLimitNum').attr('disabled',false);
    }else{
        $('#dailyLimitNum').val(0);
        $('#dailyLimitNum').attr('disabled',true);
    }
}
function toDel(id) {
    var box = WST.confirm({
        content: "您确定要删除该红包吗?", yes: function () {
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16, time: 60000});
            $.post(WST.U('admin/coupons/exchangeDel'), {couponId: id}, function (data, textStatus) {
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if (json.status == '1') {
                    WST.msg("操作成功", {icon: 1});
                    layer.close(box);
                    location.href=WST.U('Admin/coupons/exchangeIndex');
                } else {
                    WST.msg(json.msg, {icon: 2});
                }
            });
        }
    });
}
function listByPage(p){
    $('#loading').show();
    var params = {};
    params = WST.getParams('.s-ipt');
    params.key = $.trim($('#key').val());
    params.page = p;
    $.post(WST.AU('groupon://shops/pageQueryByGoods'),params,function(data,textStatus){
        $('#loading').hide();
        var json = WST.toJson(data);
        $('.j-order-row').remove();
        if(json.status==1){
            json = json.data;
            var gettpl = document.getElementById('tblist').innerHTML;
            laytpl(gettpl).render(json.data, function(html){
                $(html).insertAfter('#loadingBdy');
                $('.gImg').lazyload({ effect: "fadeIn",failurelimit : 10,skip_invisible : false,threshold: 200,placeholder:WST.conf.ROOT+'/'+WST.conf.GOODS_LOGO});
            });
            if(json.last_page>1){
                laypage({
                    cont: 'pager',
                    pages:json.last_page,
                    curr: json.current_page,
                    skin: '#e23e3d',
                    groups: 3,
                    jump: function(e, first){
                        if(!first){
                            listByPage(e.curr);
                        }
                    }
                });
            }else{

                $('#pager').empty();
            }
        }
    });
}
/**商品**/
function searchGoods(suffix){
    var params = WST.getParams('.ipt'+suffix);
    params.key = params['key'+suffix];
    params.goodsCatId = WST.ITGetGoodsCatVal('pgoodsCats1'+suffix);
    if(params.goodsCatId==''){
        WST.msg('请选择一个商品分类',{icon:2});
        return;
    }
    var loading = WST.msg('正在提交数据，请稍后...', {icon: 16,time:60000});
    $.post(WST.U('admin/recommends/searchGoods'),params,function(data,textStatus){
        layer.close(loading);
        var json = WST.toAdminJson(data);
        if(json.status=='1'){
            if(!json.data)return;
            json = json.data;
            $("#llist"+suffix).empty();
            var ids = $('#ids'+suffix).val().split(',');
            var data,html=[];
            for(var i=0;i<json.length;i++){
                data = json[i];
                if($.inArray(data.goodsId.toString(),ids)==-1){
                    html.push('<div class="trow"><div class="tck"><input type="checkbox" name="lchk'+suffix+'" class="lchk'+suffix+'" value="'+data.goodsId+'"></div>');
                    html.push('<div class="ttxt">【'+data.shopName+'】'+data.goodsName+'</div></div>');
                }
            }
            $("#llist"+suffix).html(html.join(''));
        }else{
            WST.msg(json.msg,{icon:2});
        }
    });
}
function listQueryBySearchGoods(suffix){
    suffix = (typeof(suffix)=='object')?'_2':suffix;
    $('#rlist'+suffix).empty();
    $('#ids'+suffix).val('');
    var params = {};
    params.dataType = $('#dataType'+suffix).val();
    params.goodsCatId = WST.ITGetGoodsCatVal('pgoodsCats2'+suffix);
    var loading = WST.msg('正在提交数据，请稍后...', {icon: 16,time:60000});
    $.post(WST.U('admin/recommends/listQueryByGoods'),params,function(data,textStatus){
        layer.close(loading);
        var json = WST.toAdminJson(data);
        if(json.status=='1'){
            if(json.data && json.data.length){
                json = json.data;
                var data,html=[],ids = [];
                for(var i=0;i<json.length;i++){
                    data = json[i];
                    ids.push(data.dataId);
                    html.push('<div class="trow"><div class="tck"><input type="checkbox" name="rchk'+suffix+'" class="rchk'+suffix+'" value="'+data.dataId+'"></div>');
                    html.push('<div class="ttxt">【'+data.shopName+'】'+data.goodsName+'</div>');
                    html.push('<div class="top"><input type="text" class="s-sort s-ipt'+suffix+'" value="'+data.dataSort+'" v="'+data.dataId+'"></div></div>');
                }
                $('#ids'+suffix).val(ids.join(','));
                $("#rlist"+suffix).html(html.join(''));
            }
            if(WST.ITGetGoodsCatVal('pgoodsCats1'+suffix)>0)loadGoods(suffix);
        }
    });
}

function couponMoveRight(suffix){
    $('input[name="lchk'+suffix+'"]:checked').each(function(){
        var html = [];
        html.push('<div class="trow"><div class="tck"><input type="checkbox" name="rchk'+suffix+'" class="rchk'+suffix+'" value="'+$(this).val()+'"></div>');
        html.push('<div class="ttxt">'+$(this).parent().parent().find('.ttxt').html()+'</div>');
        html.push('<div class="top" style="display:none"><input type="text" class="s-sort s-ipt'+suffix+'" value="0" v="'+$(this).val()+'"></div></div>');
        $(this).parent().parent().remove();
        $('#rlist'+suffix).append(html.join(''));
    });
    var ids = [];
    $('input[name="rchk'+suffix+'"]').each(function(){
        ids.push($(this).val());
    });
    $('#ids'+suffix).val(ids.join(','));
}
function couponMoveLeft(suffix){
    $('input[name="rchk'+suffix+'"]:checked').each(function(){
        var html = [];
        html.push('<div class="trow"><div class="tck"><input type="checkbox" name="lchk'+suffix+'" class="lchk'+suffix+'" value="'+$(this).val()+'"></div>');
        html.push('<div class="ttxt">'+$(this).parent().parent().find('.ttxt').html()+'</div></div>');
        $(this).parent().parent().remove();
        $('#llist'+suffix).append(html.join(''));
    })
}
var isInitUpload = false;
function editInit(){
    var laydate = layui.laydate;
    laydate.render({elem: '#startDate',format:'yyyy-MM-dd',type:'date'});
    laydate.render({elem: '#endDate',format:'yyyy-MM-dd',type:'date'});
    /* 表单验证 */
    $('#couponForm').validator({
        fields: {
            name: {
                rule:"required",
                msg:{required:"请输入优惠卷名称"},
                tip:"请输入优惠卷名称",
                ok:"",
            },
        },

        valid: function(form){
            var params = WST.getParams('.ipt');
            var ids = [];
            var suffix='_2';
            $('input[name="rchk'+suffix+'"]').each(function(){
                ids.push($(this).val());
            });
            // $('.s-ipt'+suffix).each(function(){
            //     params['ipt'+$(this).attr('v')] = $(this).val();
            // })
            params.useObjectIds = ids.join(',');
            //params.dataType = $('#dataType'+suffix).val();
            //params.goodsCatId = WST.ITGetGoodsCatVal('pgoodsCats2'+suffix);
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16,time:60000});
            $.post(WST.U('admin/coupons/'+((params.couponId==0)?"exchangeAdd":"exchangeEdit")),params,function(data,textStatus){
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if(json.status=='1'){
                    WST.msg("操作成功",{icon:1});
                    location.href=WST.U('Admin/coupons/exchangeIndex');
                }else{
                    WST.msg(json.msg,{icon:2});
                }
            });

        }

    });
};


