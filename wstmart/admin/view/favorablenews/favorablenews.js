var mmg;
$(function () {
    var h = WST.pageHeight();
    var cols = [
        {title:'跳转类型', name:'accessType', width: 10,renderer: function(val,item,rowIndex){
            return "<span><p class='wst-nowrap'>"+item['accessType']+"</p> </span>"
        }},
        {title:'创建者ID', name:'adminId', width: 10,renderer: function(val,item,rowIndex){
            return "<span><p class='wst-nowrap'>"+item['adminId']+"</p> </span>"
        }},
        {title:'缩略图', name:'favorablenewsPath', width: 20, renderer: function(val,item,rowIndex){
            return '<img src="'+WST.conf.ROOT+'/'+item['favorablenewsPath']+'" width="100" />'
        }},
        {title:'消息标题', name:'title', width:30, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['title']+"</p> </span>"
        }},
        {title:'消息内容', name:'text', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['text']+"</p> </span>"
        }},
        {title:'发送状态', name:'sendStatus', width:5, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['sendStatus']+"</p> </span>"
        }},
        {title:'发送时间', name:'sendTime', width:5, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['sendTime']+"</p> </span>"
        }},
        {title:'操作', name:'op' ,width:50, align:'center', renderer: function(val,item,rowIndex){
            var h = "";
            if(WST.GRANT.YHXX_01)h += "<a class='btn btn-blue' href='javascript:toEdit(" + item['id'] + ")' ><i class='fa fa-pencil'></i>修改</a> ";
            if(WST.GRANT.YHXX_01)h += "<a class='btn btn-red' href='javascript:toDel(" + item['id'] + ")'><i class='fa fa-trash-o'></i>删除</a> ";
            return h;
        }}
    ];
    mmg = $('.mmg').mmGrid({height: h-85,indexCol: true, cols: cols,method:'POST',
        url: WST.U('admin/Favorablenews/pageQuery'), fullWidthRows: true, autoLoad: true,
        plugins: [
            $('#pg').mmPaginator({})
        ]
    });
})

function loadGrid(){
    mmg.load({page:1});
}
function toEdit(id){
    location.href = WST.U('admin/favorablenews/toEdit','id='+id);
}
function toDel(id) {
    var box = WST.confirm({
        content: "您确定要删除消息吗?", yes: function () {
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16, time: 60000});
            $.post(WST.U('admin/Favorablenews/del'), {id: id}, function (data, textStatus) {
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if (json.status == '1') {
                    WST.msg("操作成功", {icon: 1});
                    layer.close(box);
                    //loadGrid();
                    location.href=WST.U('Admin/Favorablenews/index');
                } else {
                    WST.msg(json.msg, {icon: 2});
                }
            });
        }
    });
}
function editInit(){
    var laydate = layui.laydate;
    laydate.render({elem: '#startTime',format:'yyyy-MM-dd HH:mm:ss',type:'datetime'});
    laydate.render({elem: '#endTime',format:'yyyy-MM-dd HH:mm:ss',type:'datetime'});
    /* 表单验证 */
    $('#guideForm').validator({
        fields: {
            path: {
                rule:"required",
                msg:{required:"请输上传文件"},
                tip:"请输上传文件",
                ok:"",
            }

        },

        valid: function(form){
            var params = WST.getParams('.ipt');
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16,time:60000});
            $.post(WST.U('admin/Favorablenews/'+((params.id==0)?"add":"edit")),params,function(data,textStatus){
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if(json.status=='1'){
                    WST.msg("操作成功",{icon:1});
                    location.href=WST.U('Admin/Favorablenews/index');
                }else{
                    WST.msg(json.msg,{icon:2});
                }
            });

        }

    });

//文件上传
    WST.upload({
        pick:'#guidePicker',
        formData: {dir:'favorablenewsPath'},
        accept: {extensions: 'gif,jpg,jpeg,png,mp4,json',mimeTypes: 'image/jpg,image/jpeg,image/png,image/gif,video/mp4,application/json'},
        server:WST.U('admin/index/uploadFile'),
        callback:function(f){
            var json = WST.toAdminJson(f);
            if(json.status==1){
                $('#uploadMsg').empty().hide();
                //保存上传的图片路径
                $('#favorablenewsPath').val(json.route+json.name);
                $('#preview').html('<img src="'+WST.conf.ROOT+'/'+json.route+json.name+'" width="100" />');
            }else{
                WST.msg(json.msg,{icon:2});
            }
        },
        progress:function(rate){
            $('#uploadMsg').show().html('已上传'+rate+"%");
        }
    });


};


