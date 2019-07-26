jQuery.extend(jQuery.validator.messages, {
    required: "字段值不能为空",
    remote: "请修正该字段",
    email: "请输入正确格式的电子邮件",
    url: "请输入合法的网址",
    date: "请输入合法的日期",
    dateISO: "请输入合法的日期 (ISO).",
    number: "请输入合法的数字",
    digits: "只能输入整数",
    creditcard: "请输入合法的信用卡号",
    equalTo: "请再次输入相同的值",
    accept: "请输入拥有合法后缀名的字符串",
    maxlength: jQuery.validator.format("长度至多{0}位"),
    minlength: jQuery.validator.format("长度至少{0}位"),
    rangelength: jQuery.validator.format("输入字符串长度必须介于{0} 和 {1}之间"),
    range: jQuery.validator.format("请输入一个介于 {0} 和 {1} 之间的值"),
    max: jQuery.validator.format("请输入一个最大为{0} 的值"),
    min: jQuery.validator.format("请输入一个最小为{0} 的值")
});
$.validator.setDefaults({
    ignore: '',
    onfocusout: false
 });

/*使用提示：带参数的校验，必须放在属性内，如 gt='#t'
 * 不带参数的放在class内
 * */
function isIdCardNo(num) {
    var factorArr = new Array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2, 1);
    var parityBit = new Array("1", "0", "X", "9", "8", "7", "6", "5", "4", "3", "2");
    var varArray = new Array();
    var intValue;
    var lngProduct = 0;
    var intCheckDigit;
    var intStrLen = num.length;
    var idNumber = num;
// initialize
    if ((intStrLen != 15) && (intStrLen != 18)) {
        return false;
    }
// check and set value
    for (i = 0; i < intStrLen; i++) {
        varArray[i] = idNumber.charAt(i);
        if ((varArray[i] < '0' || varArray[i] > '9') && (i != 17)) {
            return false;
        } else if (i < 17) {
            varArray[i] = varArray[i] * factorArr[i];
        }
    }
    if (intStrLen == 18) {
//check date
        var date8 = idNumber.substring(6, 14);
        if (isDate8(date8) == false) {
            return false;
        }
// calculate the sum of the products
        for (i = 0; i < 17; i++) {
            lngProduct = lngProduct + varArray[i];
        }
// calculate the check digit
        intCheckDigit = parityBit[lngProduct % 11];
// check last digit
        if (varArray[17] != intCheckDigit) {
            return false;
        }
    } else {       //length is 15
//check date
        var date6 = idNumber.substring(6, 12);
        if (isDate6(date6) == false) {
            return false;
        }
    }
    return true;
}

function isDate6(sDate) {
    if (!/^[0-9]{6}$/.test(sDate)) {
        return false;
    }
    var year, month, day;
    year = sDate.substring(0, 4);
    month = sDate.substring(4, 6);
    if (year < 1700 || year > 2500)
        return false
    if (month < 1 || month > 12)
        return false
    return true
}

function isDate8(sDate) {
    if (!/^[0-9]{8}$/.test(sDate)) {
        return false;
    }
    var year, month, day;
    year = sDate.substring(0, 4);
    month = sDate.substring(4, 6);
    day = sDate.substring(6, 8);
    var iaMonthDays = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]
    if (year < 1700 || year > 2500)
        return false
    if (((year % 4 == 0) && (year % 100 != 0)) || (year % 400 == 0))
        iaMonthDays[1] = 29;
    if (month < 1 || month > 12)
        return false
    if (day < 1 || day > iaMonthDays[month - 1])
        return false
    return true
}

// 手机号码验证
jQuery.validator.addMethod("isMobile", function (value, element) {
    var length = value.length;
    var mobile = /^(((13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1}))+\d{8})$/;
    return this.optional(element) || (length == 11 && mobile.test(value));
}, "错误的手机号码格式！");

// 电话号码验证
jQuery.validator.addMethod("isTel", function (value, element) {
    var tel = /^0\d{2,3}-?\d{7,9}$/;    //电话号码格式010-12345678
    var tel_company = /^[4,8]00-?\d{3}-?\d{4}$/;
    return this.optional(element) || (tel.test(value) || tel_company.test(value));
}, "错误的电话号码格式！");
// 联系电话(手机/电话皆可)验证
// 
// 联系电话(手机/电话皆可)验证
jQuery.validator.addMethod("isPhone", function (value, element) {
    var length = value.length;
    var mobile = /^(((13[0-9]{1})|(15[0-9]{1})|(18[0-9]{1}))+\d{8})$/;
    var tel = /^0\d{2,3}-?\d{7,9}$/;
    var tel_company = /^[4,8]00-?\d{3}-?\d{4}$/;
    return this.optional(element) || (tel.test(value) || mobile.test(value) || tel_company.test(value));
}, "错误的电话|手机格式！");

// 身份证号码验证
jQuery.validator.addMethod("isIdCardNo", function (value, element) {
    return this.optional(element) || isIdCardNo(value);
}, "错误的身份证号码格式！");

//字母数字
jQuery.validator.addMethod("alnum", function (value, element) {
    return this.optional(element) || /^[a-zA-Z0-9]+$/.test(value);
}, "只能包括英文字母和数字");

// 汉字
jQuery.validator.addMethod("chcharacter", function (value, element) {
    var tel = /^[u4e00-u9fa5]+$/;
    return this.optional(element) || (!tel.test(value));
}, "请输入汉字");

// 字符最小长度验证（一个中文字符长度为2）
jQuery.validator.addMethod("stringMinLength", function (value, element, param) {
    var length = value.length;
    for (var i = 0; i < value.length; i++) {
        if (value.charCodeAt(i) > 127) {
            length++;
        }
    }
    return this.optional(element) || (length >= param);
}, $.validator.format("长度不能小于{0}!"));

// 字符最大长度验证（一个中文字符长度为2）
jQuery.validator.addMethod("stringMaxLength", function (value, element, param) {
    var length = value.length;
    for (var i = 0; i < value.length; i++) {
        if (value.charCodeAt(i) > 127) {
            length++;
        }
    }
    return this.optional(element) || (length <= param);
}, $.validator.format("长度不能大于{0}!"));

// 大于
jQuery.validator.addMethod("gt", function (value, element, param) {
    var target = $(param);
    return parseFloat(value) > parseFloat(target.val());
}, $.validator.format("输入的值不能比前值小!"));

jQuery.validator.addMethod("egt", function (value, element, param) {
    var target = $(param);
    return parseFloat(value) >= parseFloat(target.val());
}, $.validator.format("输入的值必须不能小于前值!"));

// 小于
jQuery.validator.addMethod("lt", function (value, element, param) {
    var target = $(param);
    return parseFloat(value) < parseFloat(target.val());
}, $.validator.format("输入的值不能比前值大!"));

jQuery.validator.addMethod("elt", function (value, element, param) {
    var target = $(param);
    return parseFloat(value) <= parseFloat(target.val());
}, $.validator.format("输入的值必须不能大于前值!"));