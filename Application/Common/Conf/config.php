<?php
return array(
    'Storage' =>array(
        'driver' => 'Qiniu',
        'driverConfig' => array(
            'secrectKey'     => '6PI73ne547lpifajU9oVNTZaH8cIrLS8OWtdSV34', //七牛服务器
            'accessKey'      => 'FtRPedCaXlUpK39HhesffLrOtZHgJToZYDAa8jMe', //七牛用户
            'domain'         => 'qiniu.caisuikx.com', //七牛密码
            'bucket'         => 'documents' //空间名称
        )
    ),
	'WXMBXX' => array(
		"XTXX" => array(
			array(
				'progress_type_name'=>'服务开始通知',
				'progress_situation'=>'您好！您的服务已正式启动，请点击详情查看。',
				'extended_parameter'=>array(
				)
			),
			array(
				'progress_type_name'=>'服务冻结通知',
				'progress_situation'=>'您好！您的服务已被冻结，请联系您的服务会计人员。',
				'extended_parameter'=>array(
				)
			),
			array(
				'progress_type_name'=>'服务解冻通知',
				'progress_situation'=>'您好！您的服务已解冻，将继续为您服务。',
				'extended_parameter'=>array(
				)
			),
			array(
				'progress_type_name'=>'服务结束通知',
				'progress_situation'=>'您好！您的服务已结束，期待下次为您服务。',
				'extended_parameter'=>array(
				)
			),
			array(
				'progress_type_name'=>'服务反馈通知',
				'progress_situation'=>'您好，您的服务有了新的服务反馈，请点击详情查看。',
				'extended_parameter'=>array(
				)
			),
		),
		'YSXX' => array(
			array(
				'progress_type_name'=>'核名成功通知',
				'progress_situation'=>'尊敬的客户您好，您申请的公司名称已通过工商核准！',
				'extended_parameter'=>array(
					array('field'=>'通过名称','value'=>''),
					array('field'=>'通过时间','value'=>''),
				)
			),
			array(
				'progress_type_name'=>'执照颁发通知',
				'progress_situation'=>'尊敬的客户您好，您申请公司的营业执照已颁发！',
				'extended_parameter'=>array(
					array('field'=>'营业执照','value'=>''),
					array('field'=>'颁发时间','value'=>''),
				)
			),
			array(
				'progress_type_name'=>'刻章费用通知',
				'progress_situation'=>'尊敬的客户您好，刻章需缴纳费用如下（含发票），需要您自理，谢   谢配合！',
				'extended_parameter'=>array(
					array('field'=>'公司公章','value'=>'280元/枚'),
					array('field'=>'财务专用章','value'=>'180元/枚'),
				)
			),
			array(
				'progress_type_name'=>'物品借用通知',
				'progress_situation'=>'尊敬的客户您好，办理业务需要贵司以下物品，谢谢您的配合！',
				'extended_parameter'=>array(
					array('field'=>'借用物品','value'=>'法人身份证、股东身份证、法人私章'),
					array('field'=>'使用时间','value'=>''),
				)
			),
			array(
				'progress_type_name'=>'人员预约通知',
				'progress_situation'=>'尊敬的客户您好，以下服务需要贵司人员到场，请您安排好时间准时到场，谢谢您的配合！',
				'extended_parameter'=>array(
					array('field'=>'服务项目','value'=>''),
					array('field'=>'预约人员','value'=>''),
					array('field'=>'预约场所','value'=>''),
					array('field'=>'预约时间','value'=>''),
					array('field'=>'预约地址','value'=>''),
				)
			),
			array(
				'progress_type_name'=>'发票催收通知',
				'progress_situation'=>'尊敬的客户您好，为了保证按时完成账务及税务工作，请按时整理好相关票据。',
				'extended_parameter'=>array(
					array('field'=>'服务人员','value'=>''),
					array('field'=>'联系方式','value'=>''),
					array('field'=>'催票月份','value'=>''),
				)
			),
			array(
				'progress_type_name'=>'社保申报通知',
				'progress_situation'=>'尊敬的客户您好，为保证社保按时缴纳，请及时确认社保人数、金额。如有疑问请联系我司服务人员！及时沟通反馈！',
				'extended_parameter'=>array(
					array('field'=>'社保人数','value'=>''),
					array('field'=>'社保金额','value'=>''),
				)
			),
			array(
				'progress_type_name'=>'账务完成通知',
				'progress_situation'=>'尊敬的客户您好，贵司当期账务工作已完成，如有疑问请联系我司服务人员！',
				'extended_parameter'=>array(
					array('field'=>'做账月份','value'=>''),
					array('field'=>'服务人员','value'=>''),
				)
			),
			array(
				'progress_type_name'=>'报税完成通知',
				'progress_situation'=>'尊敬的客户，贵司当期税务已申报完成，如有疑问请联系我司服务人员！',
				'extended_parameter'=>array(
					array('field'=>'印花税','value'=>''),
					array('field'=>'个人所得税','value'=>''),
					array('field'=>'增值税','value'=>''),
					array('field'=>'工会经费','value'=>''),
					array('field'=>'附加税','value'=>''),
				)
			),
		),
	),
);
