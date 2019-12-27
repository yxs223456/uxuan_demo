var mmg;
$(function () {
    var h = WST.pageHeight();
    var cols = [
        {title:'ID', name:'id', width:10},
        {title:'商品Id', name:'goodsId', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['goodsId']+"</p> </span>"
        }},
        {title:'昵称', name:'nickname', width:30, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['nickname']+"</p> </span>"
        }},
        {title:'头像', name:'userPhoto', width:30, renderer:function (val,item,rowIndex) {
            return '<img src=\"'+WST.conf.ROOT+'/'+item['userPhoto']+'\" width=\"50\" />'
        }},
        {title:'内容', name:'content', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['content']+"</p> </span>"
        }},
        {title:'商品评分', name:'goodsScore', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['goodsScore']+"</p> </span>"
        }},
        {title:'时效评分', name:'timeScore', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['timeScore']+"</p> </span>"
        }},
        {title:'是否显示', name:'isShow', width: 10,renderer: function(val,item,rowIndex){
            return '<input type="checkbox" '+((item['isShow']==1)?"checked":"")+' id="isShow" name="isShow" value="1" class="ipt" lay-skin="switch" lay-filter="isShow1" data="'+item['id']+'" lay-text="显示|隐藏">'
        }},
        {title:'操作', name:'op' ,width:50, align:'center', renderer: function(val,item,rowIndex){
            var h = "";
            // if(WST.GRANT.SPGG_02)h += "<a class='btn btn-blue' href='javascript:toEdit(" + item['id'] + ")' ><i class='fa fa-pencil'></i>修改</a> ";
            if(WST.GRANT.PJXZ_02)h += "<a class='btn btn-red' href='javascript:toDel(" + item['id'] + ")'><i class='fa fa-trash-o'></i>删除</a> ";
            return h;
        }}
    ];
    mmg = $('.mmg').mmGrid({height: h-85,indexCol: true, cols: cols,method:'POST',
        url: WST.U('admin/goodstempappraises/pageQuery'), fullWidthRows: true, autoLoad: true,
        plugins: [
            $('#pg').mmPaginator({})
        ]
    });
    mmg.on('loadSuccess',function(data){
        layui.form.render();
        layui.form.on('switch(isShow1)', function(data){
            var id = $(this).attr("data");
            if(this.checked){
                toggleIsShow(id, 1);
            }else{
                toggleIsShow(id, 0);
            }
        });
    })
})

function toggleIsShow( id, isShow){
    $.post(WST.U('admin/goodsTempAppraises/setAppraisesToggle'), {'id':id, 'isShow':isShow}, function(data, textStatus){
        var json = WST.toAdminJson(data);
        if(json.status=='1'){
            WST.msg("操作成功",{icon:1});
            //loadGrid();
        }else{
            WST.msg(json.msg,{icon:2});
        }
    })
}

function loadGrid(){
    var keyName = $("#keyName").val();
    var goodsCatPath = WST.ITGetAllGoodsCatVals('cat_0','pgoodsCats');
    mmg.load({"page":1,"keyName":keyName,"goodsCatPath":goodsCatPath.join('_')});
}
function toEdit(id){
    location.href = WST.U('admin/goodstempappraises/toEdit','id='+id);
}
function toDel(id) {
    var box = WST.confirm({
        content: "您确定要删除该规格吗?", yes: function () {
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16, time: 60000});
            $.post(WST.U('admin/goodstempappraises/del'), {id: id}, function (data, textStatus) {
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if (json.status == '1') {
                    WST.msg("操作成功", {icon: 1});
                    layer.close(box);
                    location.href=WST.U('Admin/goodstempappraises/index');
                } else {
                    WST.msg(json.msg, {icon: 2});
                }
            });
        }
    });
}
var isInitUpload = false;
function editInit(){
    var laydate = layui.laydate;
    laydate.render({elem: '#createTime',format:'yyyy-MM-dd HH:mm:ss',type:'datetime'});
    /* 表单验证 */
    $('#guideForm').validator({
        fields: {
            goodsId: {
                rule:"number",
                msg:{number:"请输入数字"},
                tip:"请输入数字",
                ok:"",
            },
            userPhoto: {
                rule:"required",
                msg:{required:"请输用户头像"},
                tip:"请输用户头像",
                ok:"",
            },
            nickname: {
                rule:"required",
                msg:{required:"请输昵称"},
                tip:"请输昵称",
                ok:"",
            },
            goodsScore: {
                rule:"required",
                msg:{required:"请输入商品评分"},
                tip:"请输入商品评价",
            },
            timeScore: {
                rule:"required",
                msg:{required:"请输入时效评分"},
                tip:"请输入时效评分",
            },
        },

        valid: function(form){
            var params = WST.getParams('.ipt');
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16,time:60000});
            $.post(WST.U('admin/goodstempappraises/'+((params.id==0)?"add":"edit")),params,function(data,textStatus){
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if(json.status=='1'){
                    WST.msg("操作成功",{icon:1});
                    location.href=WST.U('Admin/goodstempappraises/index');
                }else{
                    WST.msg(json.msg,{icon:2});
                }
            });

        }

    });
    //initTime('#createTime',opts.createTime);
//文件上传
    WST.upload({
        pick:'#guidePicker',
        formData: {dir:'goodstempappraises'},
        accept: {extensions: 'gif,jpg,jpeg,png,mp4',mimeTypes: 'image/jpg,image/jpeg,image/png,image/gif,video/mp4'},
        server:WST.U('admin/index/uploadFile'),
        callback:function(f){
            var json = WST.toAdminJson(f);
            if(json.status==1){
                $('#uploadMsg').empty().hide();
                //保存上传的图片路径
                $('#userPhoto').val(json.route+json.name);
                $('#preview').html('<img src="'+WST.conf.ROOT+'/'+json.route+json.name+'" width="100" />');
            }else{
                WST.msg(json.msg,{icon:2});
            }
        },
        progress:function(rate){
            $('#uploadMsg').show().html('已上传'+rate+"%");
        }
    });

    WST.upload({
        pick:'#onePicker',
        formData: {dir:'temp'},
        accept: {extensions: 'gif,jpg,jpeg,png,mp4',mimeTypes: 'image/jpg,image/jpeg,image/png,image/gif,video/mp4'},
        server:WST.U('admin/index/uploadFile'),
        callback:function(f){
            var json = WST.toAdminJson(f);
            if(json.status==1){
                $('#uploadMsg1').empty().hide();
                //保存上传的图片路径
                $('#images1').val(json.route+json.name);
                $('#preview1').html('<img src="'+WST.conf.ROOT+'/'+json.route+json.name+'" width="100" />');
            }else{
                WST.msg(json.msg,{icon:2});
            }
        },
        progress:function(rate){
            $('#uploadMsg1').show().html('已上传'+rate+"%");
        }
    });
    WST.upload({
        pick:'#twoPicker',
        formData: {dir:'temp'},
        accept: {extensions: 'gif,jpg,jpeg,png,mp4',mimeTypes: 'image/jpg,image/jpeg,image/png,image/gif,video/mp4'},
        server:WST.U('admin/index/uploadFile'),
        callback:function(f){
            var json = WST.toAdminJson(f);
            if(json.status==1){
                $('#uploadMsg2').empty().hide();
                //保存上传的图片路径
                $('#images2').val(json.route+json.name);
                $('#preview2').html('<img src="'+WST.conf.ROOT+'/'+json.route+json.name+'" width="100" />');
            }else{
                WST.msg(json.msg,{icon:2});
            }
        },
        progress:function(rate){
            $('#uploadMsg2').show().html('已上传'+rate+"%");
        }
    });
    WST.upload({
        pick:'#threePicker',
        formData: {dir:'temp'},
        accept: {extensions: 'gif,jpg,jpeg,png,mp4',mimeTypes: 'image/jpg,image/jpeg,image/png,image/gif,video/mp4'},
        server:WST.U('admin/index/uploadFile'),
        callback:function(f){
            var json = WST.toAdminJson(f);
            if(json.status==1){
                $('#uploadMsg3').empty().hide();
                //保存上传的图片路径
                $('#images3').val(json.route+json.name);
                $('#preview3').html('<img src="'+WST.conf.ROOT+'/'+json.route+json.name+'" width="100" />');
            }else{
                WST.msg(json.msg,{icon:2});
            }
        },
        progress:function(rate){
            $('#uploadMsg3').show().html('已上传'+rate+"%");
        }
    });
    WST.upload({
        pick:'#fourPicker',
        formData: {dir:'temp'},
        accept: {extensions: 'gif,jpg,jpeg,png,mp4',mimeTypes: 'image/jpg,image/jpeg,image/png,image/gif,video/mp4'},
        server:WST.U('admin/index/uploadFile'),
        callback:function(f){
            var json = WST.toAdminJson(f);
            if(json.status==1){
                $('#uploadMsg4').empty().hide();
                //保存上传的图片路径
                $('#images4').val(json.route+json.name);
                $('#preview4').html('<img src="'+WST.conf.ROOT+'/'+json.route+json.name+'" width="100" />');
            }else{
                WST.msg(json.msg,{icon:2});
            }
        },
        progress:function(rate){
            $('#uploadMsg4').show().html('已上传'+rate+"%");
        }
    });
    WST.upload({
        pick:'#fivePicker',
        formData: {dir:'temp'},
        accept: {extensions: 'gif,jpg,jpeg,png,mp4',mimeTypes: 'image/jpg,image/jpeg,image/png,image/gif,video/mp4'},
        server:WST.U('admin/index/uploadFile'),
        callback:function(f){
            var json = WST.toAdminJson(f);
            if(json.status==1){
                $('#uploadMsg5').empty().hide();
                //保存上传的图片路径
                $('#images5').val(json.route+json.name);
                $('#preview5').html('<img src="'+WST.conf.ROOT+'/'+json.route+json.name+'" width="100" />');
            }else{
                WST.msg(json.msg,{icon:2});
            }
        },
        progress:function(rate){
            $('#uploadMsg5').show().html('已上传'+rate+"%");
        }
    });
    WST.upload({
        pick:'#sixPicker',
        formData: {dir:'temp'},
        accept: {extensions: 'gif,jpg,jpeg,png,mp4',mimeTypes: 'image/jpg,image/jpeg,image/png,image/gif,video/mp4'},
        server:WST.U('admin/index/uploadFile'),
        callback:function(f){
            var json = WST.toAdminJson(f);
            if(json.status==1){
                $('#uploadMsg6').empty().hide();
                //保存上传的图片路径
                $('#images6').val(json.route+json.name);
                $('#preview6').html('<img src="'+WST.conf.ROOT+'/'+json.route+json.name+'" width="100" />');
            }else{
                WST.msg(json.msg,{icon:2});
            }
        },
        progress:function(rate){
            $('#uploadMsg6').show().html('已上传'+rate+"%");
        }
    });

    function initTime($id,val){
        var html = [],t0,t1;
        var str = val.split(':');
        for(var i=0;i<24;i++){
            t0 = (val.indexOf(':00')>-1 && (parseInt(str[0],10)==i))?'selected':'';
            t1 = (val.indexOf(':30')>-1 && (parseInt(str[0],10)==i))?'selected':'';
            html.push('<option value="'+i+':00" '+t0+'>'+i+':00</option>');
            html.push('<option value="'+i+':30" '+t1+'>'+i+':30</option>');
        }
        $($id).append(html.join(''));
    }
};


