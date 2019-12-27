var mmg;
$(function () {
    var h = WST.pageHeight();
    var cols = [
        // {title:'ID', name:'guideId', width:10},
        {title:'类型', name:'type', width: 10,renderer: function(val,item,rowIndex){
                return "<span><p class='wst-nowrap'>"+item['type']+"</p> </span>"
        }},
        {title:'&nbsp;', name:'path', width: 20, renderer: function(val,item,rowIndex){
                return '<img src="'+item['path']+'" width="100" />'
        }},
        {title:'访问地址', name:'link', width:30, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['link']+"</p> </span>"
        }},
        {title:'有效时间', name:'time', width:30, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['startTime']+"-"+item['endTime']+"</p> </span>"
        }},
        {title:'目标渠道', name:'targetChannel', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['targetChannel']+"</p> </span>"
        }},
        {title:'图片大小', name:'proportion', width:10, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['proportion']+"</p> </span>"
        }},
        {title:'排序', name:'sort', width:5, renderer:function (val,item,rowIndex) {
            return "<span><p class='wst-nowrap'>"+item['sort']+"</p> </span>"
        }},
        {title:'是否显示', name:'status', width: 10,renderer: function(val,item,rowIndex){
            return '<input type="checkbox" '+((item['status']==1)?"checked":"")+' id="isShow" name="isShow" value="1" class="ipt" lay-skin="switch" lay-filter="isShow1" data="'+item['guideId']+'" lay-text="显示|隐藏">'
        }},
        {title:'操作', name:'op' ,width:50, align:'center', renderer: function(val,item,rowIndex){
            var h = "";
            if(WST.GRANT.KPGL_2)h += "<a class='btn btn-blue' href='javascript:toEdit(" + item['guideId'] + ")' ><i class='fa fa-pencil'></i>修改</a> ";
            if(WST.GRANT.KPGL_2)h += "<a class='btn btn-red' href='javascript:toDel(" + item['guideId'] + ")'><i class='fa fa-trash-o'></i>删除</a> ";
            return h;
        }}
    ];
    mmg = $('.mmg').mmGrid({height: h-85,indexCol: true, cols: cols,method:'POST',
        url: WST.U('admin/guide/pageQuery'), fullWidthRows: true, autoLoad: true,
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

function toggleIsShow( guideId, isShow){
    $.post(WST.U('admin/guide/setToggle'), {'catId':guideId, 'isShow':isShow}, function(data, textStatus){
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
    mmg.load({page:1});
}
function toEdit(id){
    location.href = WST.U('admin/guide/toEdit','id='+id);
}
function toDel(guideId) {
    var box = WST.confirm({
        content: "您确定要删除该开屏配置吗?", yes: function () {
            var loading = WST.msg('正在提交数据，请稍后...', {icon: 16, time: 60000});
            $.post(WST.U('admin/guide/del'), {guideId: guideId}, function (data, textStatus) {
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if (json.status == '1') {
                    WST.msg("操作成功", {icon: 1});
                    layer.close(box);
                    //loadGrid();
                    location.href=WST.U('Admin/guide/index');
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
            $.post(WST.U('admin/guide/'+((params.guideId==0)?"add":"edit")),params,function(data,textStatus){
                layer.close(loading);
                var json = WST.toAdminJson(data);
                if(json.status=='1'){
                    WST.msg("操作成功",{icon:1});
                    location.href=WST.U('Admin/guide/index');
                }else{
                    WST.msg(json.msg,{icon:2});
                }
            });

        }

    });

//文件上传
    WST.upload({
        pick:'#guidePicker',
        formData: {dir:'sysconfigs'},
        accept: {extensions: 'gif,jpg,jpeg,png,mp4,json',mimeTypes: 'image/jpg,image/jpeg,image/png,image/gif,video/mp4,application/json'},
        server:WST.U('admin/index/uploadFile'),
        callback:function(f){
            var json = WST.toAdminJson(f);
            if(json.status==1){
                $('#uploadMsg').empty().hide();
                //保存上传的图片路径
                $('#path').val(json.route+json.name);
                $('#preview').html('<img src="'+json.route+json.name+'" width="100" />');
            }else{
                WST.msg(json.msg,{icon:2});
            }
        },
        progress:function(rate){
            $('#uploadMsg').show().html('已上传'+rate+"%");
        }
    });


};


