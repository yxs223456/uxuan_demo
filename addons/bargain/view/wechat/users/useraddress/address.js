jQuery.noConflict();
//新增或编辑收货地址页
function editAddress(addressId){
	$('#wst-switch').html('');
	$('#username').val('');
	$('#cellphone').val('');
	$('#address_detailed').val('');
	$('#areaId').val('');
	$('#addresst').html('请选择收货地址');
	$('.wst-ad-submit .button').attr('onclick','javascript:saveAddress('+addressId+');');
	$('#defaults').removeClass('default').addClass('nodefault');
    if(addressId>0){
    	$('.iziModal-header-title').html('修改收货地址');
        $.post(WST.U('wechat/useraddress/getById'), {addressId:addressId}, function(data){
            var info = WST.toJson(data);
            if(info){
                $('#username').val(info.userName);
                $('#cellphone').val(info.userPhone);
                $('#address_detailed').val(info.userAddress);
                $('#areaId').val(info.areaId);
                if(info.isDefault==1){
                	$('#defaults').removeClass('nodefault').addClass('default');
                }else{
                	$('#defaults').removeClass('default').addClass('nodefault');
                }
                $('#addresst').html(info.areaName);
            }
            addressInfo= null;
        });
    }else{
    	$('.iziModal-header-title').html('新增收货地址');
    }
    jQuery('#modal-large').iziModal('open',this);
}
jQuery("#modal-large").iziModal({
    title: "新增收货地址",
    subtitle: "",
    iconClass: 'icon-chat',
    overlayColor: 'rgba(0, 0 0, 0.6)',
    headerColor: '#ffffff'
});
//保存收货地址
function saveAddress(addressId){
    var userName = $('#username').val();
    var userPhone = $('#cellphone').val();
    var areaId = $('#areaId').val();
    var userAddress = $('#address_detailed').val();
    if( $('#defaults').attr('class').indexOf('nodefault') > -1 ){
        var isdefaultAddress = 0;//不设为默认地址
    }else{
        var isdefaultAddress = 1;//设为默认地址
    }
    if(userName==''){
    	WST.msg('收货人名称不能为空','info');
	    $('#username').focus();
        return false;
    }
    if(userPhone==''){
    	WST.msg('联系电话不能为空','info');
        return false;
    }
    if(areaId==''){
    	WST.msg('请选择地址','info');
	    $('#areaId').focus();
        return false;
    }
    if(userAddress==''){
    	WST.msg('请填写详细地址','info');
	    $('#address_detailed').focus();
        return false;
    }
    var param = {};
    param.addressId = addressId;
    param.userName = userName;
    param.areaId = areaId;
    param.userPhone = userPhone;
    param.userAddress = userAddress;
    param.isDefault = isdefaultAddress;
	$('.wst-ad-submit .button').addClass("active").attr('disabled', 'disabled');
    $.post(WST.U('wechat/useraddress/edits'), param, function(data){
        var json = WST.toJson(data);
        if( json.status == 1 ){
        	WST.msg(json.msg,'success');
        	var type = $('#type').val();
        	var id = $('#addressId2').val();
        	if(param.addressId==0 && type==1)var addId = json.data.addressId;
            setTimeout(function(){
            	if(param.addressId==0 && type==1){
            		chooseAddress(addId);
            	}else{
            		location.href = WST.AU('bargain://useraddress/index','type='+type+'&addressId='+id);
            	}
            },1500);
        }else{
        	WST.msg(json.msg,'warn');
        	setTimeout(function(){
            	$('.wst-ad-submit .button').removeAttr('disabled').removeClass("active");
            },1500);
        }
        data = json = null;
    });
}
//设为默认地址
function inDefault(obj,id){
	$(obj).addClass('default').removeClass('nodefault').siblings('.j-operate').addClass('nodefault').removeClass('default');
	$('.wst-ad-operate').css('position','relative');
    $.post(WST.U('wechat/useraddress/setDefault'), {id:id}, function(data){
        var json = WST.toJson(data);
        if( json.status == 1 ){
        	WST.msg(json.msg,'success');
            setTimeout(function(){
            	location.href = WST.AU('bargain://useraddress/index');
            },1500);
        }else{
        	WST.msg(json.msg,'warn');
        	$('.wst-ad-operate').css('position','static');
        }
        data = json = null;
    });
}
function setToDefault(obj){
    if( $(obj).attr('class').indexOf('nodefault') > -1 ){
        $(obj).removeClass('nodefault').addClass('default');
    }else{
        $(obj).removeClass('default').addClass('nodefault');
    }
}
//删除收货地址
function delAddress(addressId){
	WST.dialog('确定删除吗？','toDelAddress('+addressId+')');
}
//删除收货地址
function toDelAddress(addressId){
    $.post(WST.U('wechat/useraddress/del'), {id:addressId}, function(data){
        var json = WST.toJson(data);
        if(json.status==1){
        	WST.msg(json.msg,'success');
            setTimeout(function(){
            	var type = $('#type').val();
            	var id = $('#addressId2').val();
            	location.href = WST.AU('bargain://useraddress/index','type='+type+'&addressId='+id);
            	
            },2000);
        }else{
        	WST.msg(json.msg,'warn');
        }
        WST.dialogHide('prompt');
        data = json = null;
    });
}
//地址选择
function inOption(obj,n){
	$(obj).addClass('active').siblings().removeClass('active');
	$('.area_'+n).removeClass('hide').siblings('.list').addClass('hide');
	var level = $('#level').val();
	var n = n+1;
	for(var i=n; i<=level; i++){
		$('.area_'+i).remove();
		$('.active_'+i).remove();
	}
}
function inChoice(obj,id,val,level){
	$('#level').val((level+1));
	$(obj).addClass('active').siblings().removeClass('active');
	$('#'+id).attr('areaId',val);
	$('.active_'+level).removeClass('active').html($(obj).html());
	WST.ITAreas({id:id,val:val,className:'j-areas'});
}
/**
 * 循环创建地区
 * @param id            当前分类ID
 * @param val           当前分类值
 * @param className     样式，方便将来获取值
 */
WST.ITAreas = function(opts){
	opts.className = opts.className?opts.className:"j-areas";
	var obj = $('#'+opts.id);
	obj.attr('lastarea',1);
	$.post(WST.U('wechat/areas/listQuery'),{parentId:opts.val},function(data,textStatus){
	     var json = WST.toJson(data);
	     if(json.data && json.data.length>0){
	    	 json = json.data;
	         var html = [],tmp;
	         var tid = opts.id+"_"+opts.val;
	     	 var level = parseInt(obj.attr('level'),10);
	    	 $('.area_'+level).addClass('hide');
	    	 var level = level+1;
	         html.push('<div id="'+tid+'" class="list '+opts.className+' area_'+level+'" areaId="0" level="'+level+'">');
		     for(var i=0;i<json.length;i++){
		    	 tmp = json[i];
		         html.push("<p onclick='javascript:inChoice(this,\""+tid+"\","+tmp.areaId+","+level+");'>"+tmp.areaName+"</p>");
		     }
	         html.push('</div>');
		     $(html.join('')).insertAfter('#'+opts.id);
		     var h = WST.pageHeight();
		     var listh = h/2-106;
		     $(".wst-fr-box .list").css('overflow-y','scroll').css('height',listh+'px');
		     $(".wst-fr-box .option").append('<p class="ui-nowrap-flex term active_'+level+' active" onclick="javascript:inOption(this,'+level+')">请选择</p>');
	     }else{
	    	 opts.isLast = true;
	    	 opts.lastVal = opts.val;
	    	 $('#areaId').val(opts.lastVal);
	    	 var ht = '';
	 		$('.wst-fr-box .term').each(function(){
	 			ht += $(this).html();
			});
	 		$('#addresst').html(ht);
	 		dataHide();
	     }
	});
}
function chooseAddress(id){
	location.href = WST.AU('bargain://carts/wxSettlement','addressId='+id);
}
$(document).ready(function(){
	WST.initFooter('user');
    // 弹出层
    $('#modal-large').css({'top':0,'margin-top':0});
    var h = WST.pageHeight();
    $("#frame").css('bottom','-'+h/2);
    var listh = h/2-106;
    $(".wst-fr-box .list").css('overflow-y','scroll').css('height',listh+'px');
});
//弹框
function dataShow(){
	jQuery('#cover').attr("onclick","javascript:dataHide();").show();
	jQuery('#frame').animate({"bottom": 0}, 500);
}
function dataHide(){
	var dataHeight = $("#frame").css('height');
	jQuery('#frame').animate({'bottom': '-'+dataHeight}, 500);
	jQuery('#cover').hide();
}