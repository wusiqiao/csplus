function createStaffSelectDialog(el){
	console.log(547);
	return new Vue({
       el: el,
       data:{
       	 staff_unselected: {}
       },
   		template: `
    		<div class="showEmployees showWrap">
		        <div class="employeesBox showBox">
		            <div class="employeesHead showHead">
		                <p id="employeesTitle">添加部门人员-业务部</p>
		                <img class="close" src="{$Think.MODULE_PATH}/Organization/x8.png" alt="">
		            </div>
		            <div class="employeesSearch">
		                <input type="text" name="search_staff" placeholder="请输入人员名称或部门名称" />
		                <img src="{$Think.const.IMG_URL}/Organization/x7.png" alt="" onclick="searchStaffForAdjust()">
		            </div>
		            <div class="basic-info arrangementWrap mb20">
		                <div class="title">
		                    <span>已选用户</span>
		                    <img class="arrow" src="{$Think.const.IMG_URL}/Organization/x9.png" alt="" onclick="searchStaffForAdjust()">
		                </div>
		                <div class="flexWrap">
		                    <div class="flex-wrap topic pt20 arrangement" style="" id="checkedUser">
		                    </div>
		                </div>
		            </div>
		            <div id="">
		                <div class="">
		                    <p class="waitPlople">待选人员：</p>
		                    <div id="" style="height:3rem;overflow-y:scroll">
		                        <ul>
		                            <li v-for="(item,index) in staff_unselected">
		                                <span class="two">
											{{item.touxiang}}
										</span>
		                                <span class="three">{{item.xingming}}</span>
		                                <span class="four" style="padding-right:20px">{{item.bumen}}</span>
		                            </li>
		                        </ul>
		                    </div>
		                </div>
		            </div>
		            <div class="employeesFoot showFoot">
		                <span class="cancel">取消</span>
		                <span class="sure">确认</span>
		            </div>
		        </div>
		    </div>
    	`
   })
}