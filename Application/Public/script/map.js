var layIndex = 0;
var map = null;

function openMapWindow() {
    $("#baidumap").show();
    if (!map) {
        map = createMap("mapinfo", new BMap.Point(118.143411, 24.498636), 13);
        var myCity = new BMap.LocalCity();
        myCity.get(localCity_callback);
        map.addEventListener("click", mapClick);
        $("#baidumap .r_border").click(function () {
            searchMap();
        });
        $("#suggestId").keydown(function (e) {
            if (event.keyCode == 13) {
                searchMap();
            }
        });
    }
}

//搜索调用 
function searchMap() {
    var txt = $("#suggestId").val();
    if (txt) {
        var localSearchOptions = {
            renderOptions: {
                map: map,
                panel: "s-result"
            },
            pageCapacity: 3,
            onInfoHtmlSet: function (poi) {
                confirm(poi.point, poi.title);
            }
        };
        var local = new BMap.LocalSearch(map, localSearchOptions);
        map.clearOverlays();
        local.search(txt);
    }
}
//确定选取该点
function confirm(pt, addr) {
    $.dialog.dialogConfirm("确定以"+ addr +"作为中心坐标？",
                        function () {
                            //location.replace(location.href);
                        },
                        function () {
                            $.dialog.dialogCose()
                        }
   );
}
// 百度地图API功能       
function mapClick(e) {
    var pt = e.point;
    var gc = new BMap.Geocoder();
    gc.getLocation(pt, function (rs) {
        var addComp = rs.addressComponents;
        var addr = addComp.province + addComp.city + addComp.district + addComp.street + addComp.streetNumber;
        confirm(pt, addr);
    });
    clearOverlays();
    addOverlay(pt);
}

function localCity_callback(result) {
    var cityName = result.name;
    map.setCenter(cityName);
}

function createMap(id, centerPos, zoom) {
    var baiduMap = new BMap.Map(id);
    baiduMap.centerAndZoom(centerPos, zoom);
    baiduMap.enableScrollWheelZoom(true);
    baiduMap.enableContinuousZoom();
    baiduMap.addControl(new BMap.NavigationControl());  //添加默认缩放平移控件            
    return baiduMap;
}

function addOverlay(pt) {
    var marker1 = new BMap.Marker(pt);  // 创建标注 
    map.addOverlay(marker1);
}

function clearOverlays() {
    var lays = map.getOverlays();
    for (var i = 0; i < lays.length; i++) {
        if ((typeof lays[i].getIcon) === 'function') {
            if (lays[i].getIcon().imageUrl.indexOf('point') > -1) {
                map.removeOverlay(lays[i]);
            }
        }
    }
}