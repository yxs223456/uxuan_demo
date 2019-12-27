<?php
// 加载公共函数文件
include Env::get('root_path').'wstmart/common/common/function.php';
include Env::get('root_path').'wstmart/common/common/tool.php';
include Env::get('root_path').'wstmart/home/common/function.php';
include Env::get('root_path').'wstmart/admin/common/function.php';

//加载公共异常返回文件
include Env::get('root_path').'wstmart/common/exception/AppException.php';