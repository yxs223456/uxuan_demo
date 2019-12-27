function queryByPage(p){
	$('#list').html('<tr><td colspan="11"><img src="'+WST.conf.ROOT+'/static/images/loading_16x16.gif">正在加载数据...</td></tr>');
	var params = {};
	params = {};
	params.useCondition = $.trim($('#useCondition').val());
	params.page = p;
	$.post(WST.AU('coupon://shops/pageQuery'),params,function(data,textStatus){
	    var json = WST.toJson(data);
	    if(json.status==1 && json.data){
	    	if(params.page>json.last_page && json.last_page >0){
               queryByPage(json.last_page);
               return;
            }
	       	var gettpl = document.getElementById('couponstpl').innerHTML;
	       	laytpl(gettpl).render(json.data, function(html){
	       		$('#couponslist').html(html);
	       	});
	       	laypage({
		        	 cont: 'pager', 
		        	 pages:json.last_page, 
		        	 curr: json.current_page,
		        	 skin: '#e23e3d',
		        	 groups: 3,
		        	 jump: function(e, first){
		        		    if(!first){
		        		    	queryByPage(e.curr);
		        		    }
		        	 } 
		    });
       	}  
	});
}

function grantQueryByPage(p){
    $('#list').html('<tr><td colspan="11"><img src="'+WST.conf.ROOT+'/static/images/loading_16x16.gif">正在加载数据...</td></tr>');
    var params = {};
    params = {};
    params.useCondition = $.trim($('#useCondition').val());
    params.page = p;
    $.post(WST.AU('coupon://shops/grantQueryByPage'),params,function(data,textStatus){
        var json = WST.toJson(data);
        if(json.status==1 && json.data){
            if(params.page>json.last_page && json.last_page >0){
                queryByPage(json.last_page);
                return;
            }
            var gettpl = document.getElementById('couponstpl').innerHTML;
            laytpl(gettpl).render(json.data, function(html){
                $('#grantCouponslist').html(html);
            });
            laypage({
                cont: 'pager',
                pages:json.last_page,
                curr: json.current_page,
                skin: '#e23e3d',
                groups: 3,
                jump: function(e, first){
                    if(!first){
                        queryByPage(e.curr);
                    }
                }
            });
        }
    });
}
function grantCoupons(id) {
    var ll = WST.load({msg:'正在加载信息，请稍候...'});
	layer.close(ll);
	var w = WST.open({
		type: 1,
		title:"发放红包",
		shade: [0.6, '#000'],
		border: [0],
		content: '<textarea id="grantCouponPhone" rows="10" cols="70" placeholder="多个用英文逗号隔开"></textarea>',
		area: ['500px', '320px'],
		btn: ['发放', '关闭窗口'],
		yes: function(index, layero){
			var params = {};
			params.id = id;
			params.grantCouponPhone = $("#grantCouponPhone").val();
			if(params.id=='' && params.grantCouponPhone==''){
				WST.msg('请输入发放已注册手机号',{icon:2});
				return;
			}
			ll = WST.load({msg:'数据处理中，请稍候...'});
			$.post(WST.AU('coupon://shops/grantCouponByPhone'),params,function(data){
				layer.close(ll);
				var json = WST.toJson(data);
				console.log(json);
				if(json.status===1){
					if (json.data.num!=='') {
						if (json.data.num>0) {
							$("#grantStatus_"+id).html("还可发放"+json.data.num+"人");
						}else if(json.data.num===0){
                            $("#grantStatus_"+id).html("已发放");
						}
					}
                    WST.msg(json.msg, {icon: 1});
					layer.close(w);
				}else{
					WST.msg(json.msg, {icon: 2});
				}
			});
		}
	});
}
function checkUseCondition(v){
    if(v==1){
    	$('#useMoney').attr('disabled',false);
    }else{
    	$('#useMoney').val(0);
    	$('#useMoney').attr('disabled',true);
    }
}
function checkExchange(v){
    if(v==1){
        $('#integral').attr('disabled',false);
    }else{
        $('#integral').val(0);
        $('#integral').attr('disabled',true);
    }
}
function toEdit(id){
    location.href = WST.AU('coupon://shops/edit','id='+id);
}
function toView(id){
	location.href = WST.AU('coupon://goods/detail','id='+id);
}

function save(){
    $('#couponform').isValid(function(v){
		if(v){
			var params = WST.getParams('.ipt');
			var loading = WST.load({msg:'正在提交数据，请稍后...'});
			$.post(WST.AU("coupon://shops/toEdit"),params,function(data,textStatus){
				layer.close(loading);
			    var json = WST.toJson(data);
			    if(json.status==1){
		            WST.msg(json.msg,{icon:1},function(){
		            	location.href = WST.AU('coupon://shops/index');
		            });
			    }else{
			    	WST.msg(json.msg,{icon:2});
			    }
			});
		}
	});
}
function del(id){
	var box = WST.confirm({content:"您确定删除该优惠券吗?",yes:function(){
		layer.close(box);
		var loading = WST.load({msg:'正在提交请求，请稍后...'});
		$.post(WST.AU("coupon://shops/del"),{id:id},function(data,textStatus){
			layer.close(loading);
		    var json = WST.toJson(data);
			if(json.status==1){
			    WST.msg(json.msg,{icon:1},function(){
			        queryByPage(WSTCurrPage);
			    });
		    }else{
				WST.msg(json.msg,{icon:2});
			}
		});
	}});
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

function searchGoods(){
	var params = WST.getParams('.s-ipt');
    var loading = WST.load({msg:'正在查询数据，请稍后...'});
	$.post(WST.AU("coupon://shops/searchGoods"),params,function(data,textStatus){
		layer.close(loading);
	    var json = WST.toJson(data);
	    $('#goodsSearchBox').empty();
	    if(json.status==1 && json.data){
	    	var gettpl = document.getElementById('tblist').innerHTML;
	       	laytpl(gettpl).render(json.data, function(html){
	       		$('#goodsSearchBox').html(html);
	       	});
	    }
	});
}

function moveRight(){
	if($('.lchk').size()<=0)return;
	var ids = $('#useObjectIds').val();
	if(ids.length>0){
		ids = ids.split(',');
	}else{
		ids = [];
	}
	$('.lchk').each(function(){
		if($(this)[0].checked){
	        $(this).attr('class','rchk');
	        $('#goodsResultBox').append($(this).parent().parent());
	        ids.push($(this).val());
	    }
	})
	$('#useObjectIds').val(ids.join(','));
}

function moveLeft(){
	if($('.rchk').size()<=0)return;
	var ids = $('#useObjectIds').val().split(',');
	$('.rchk').each(function(){
		if($(this)[0].checked){
	        $(this).attr('class','lchk');
	        $('#goodsSearchBox').append($(this).parent().parent());
	        for(var i=0;i<ids.length;i++){
	        	if(ids[i]==$(this).val())ids.splice(i, 1);
	        }
	    }
	})
    $('#useObjectIds').val(ids.join(','));
}