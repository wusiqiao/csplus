(function ($) {

    $.extend(Array.prototype, {indexOf: function (o) {

            for (var i = 0, len = this.length; i < len; i++) {

                if (this[i] == o) {

                    return i;
                }

            }

            return -1;
        }, remove: function (o) {

            var index = this.indexOf(o);
            if (index != -1) {

                this.splice(index, 1);
            }

            return this;
        }, removeById: function (filed, id) {

            for (var i = 0, len = this.length; i < len; i++) {

                if (this[i][filed] == id) {

                    this.splice(i, 1);
                    return this;
                }

            }

            return this;
        }

    });
    function setSize(target, param) {

        var opts = $.data(target, "datagrid").options;
        var pnl = $.data(target, "datagrid").panel;
        if (param) {

            if (param.width) {

                opts.width = param.width;
            }

            if (param.height) {

                opts.height = param.height;
            }

        }

        if (opts.fit == true) {

            var p = pnl.panel("panel").parent();
            opts.width = p.width();
            opts.height = p.height();
        }

        pnl.panel("resize", {width: opts.width, height: opts.height});
    }
    ;
    function fixDataGridHeight(target) {

        var opts = $.data(target, "datagrid").options;
        var pnl = $.data(target, "datagrid").panel;
        var pnlwidth = pnl.width();
        var pnlheight = pnl.height();
        var view = pnl.children("div.datagrid-view");
        var view1 = view.children("div.datagrid-view1");
        var view2 = view.children("div.datagrid-view2");
        var view1header = view1.children("div.datagrid-header");
        var view2header = view2.children("div.datagrid-header");
        var view1headerTable = view1header.find("table");
        var view2headerTable = view2header.find("table");
        view.width(pnlwidth);
        var view1headerInner = view1header.children("div.datagrid-header-inner").show();
        view1.width(view1headerInner.find("table").width());
        if (!opts.showHeader) {

            view1headerInner.hide();
        }

        view2.width(pnlwidth - view1.outerWidth());
        view1.children("div.datagrid-header,div.datagrid-body,div.datagrid-footer").width(view1.width());
        view2.children("div.datagrid-header,div.datagrid-body,div.datagrid-footer").width(view2.width());
        var hh;
        view1header.css("height", "");
        view2header.css("height", "");
        view1headerTable.css("height", "");
        view2headerTable.css("height", "");
        hh = Math.max(view1headerTable.height(), view2headerTable.height());
        view1headerTable.height(hh);
        view2headerTable.height(hh);
        if ($.boxModel == true) {

            view1header.height(hh - (view1header.outerHeight() - view1header.height()));
            view2header.height(hh - (view2header.outerHeight() - view2header.height()));
        } else {

            view1header.height(hh);
            view2header.height(hh);
        }

        if (opts.height != "auto") {

            var bodyHeight = pnlheight - view2.children("div.datagrid-header").outerHeight(true) - view2.children("div.datagrid-footer").outerHeight(true) - pnl.children("div.datagrid-toolbar").outerHeight(true) - pnl.children("div.datagrid-pager").outerHeight(true);
            view1.children("div.datagrid-body").height(bodyHeight);
            view2.children("div.datagrid-body").height(bodyHeight);
        }

        view.height(view2.height());
        view2.css("left", view1.outerWidth());
    }
    ;
    function fixRowHeight(target, index) {

        var rows = $.data(target, "datagrid").data.rows;
        var opts = $.data(target, "datagrid").options;
        var pnl = $.data(target, "datagrid").panel;
        var view = pnl.children("div.datagrid-view");
        var view1 = view.children("div.datagrid-view1");
        var view2 = view.children("div.datagrid-view2");
        if (!view1.find("div.datagrid-body-inner").is(":empty")) {

            if (index >= 0) {

                fixSingleRowHeight(index);
            } else {

                for (var i = 0; i < rows.length; i++) {

                    fixSingleRowHeight(i);
                }

                if (opts.showFooter) {

                    var footerRows = $(target).datagrid("getFooterRows") || [];
                    var c1 = view1.children("div.datagrid-footer");
                    var c2 = view2.children("div.datagrid-footer");
                    for (var i = 0; i < footerRows.length; i++) {

                        fixSingleRowHeight(i, c1, c2);
                    }

                    fixDataGridHeight(target);
                }

            }

        }

        if (opts.height == "auto") {

            var view1body = view1.children("div.datagrid-body");
            var view2body = view2.children("div.datagrid-body");
            var outerHeightTotal = 0;
            var tmpHeight = 0;
            view2body.children().each(function () {

                var c = $(this);
                if (c.is(":visible")) {

                    outerHeightTotal += c.outerHeight();
                    if (tmpHeight < c.outerWidth()) {

                        tmpHeight = c.outerWidth();
                    }

                }

            });
            if (tmpHeight > view2body.width()) {

                outerHeightTotal += 18;
            }

            view1body.height(outerHeightTotal);
            view2body.height(outerHeightTotal);
            view.height(view2.height());
        }

        view2.children("div.datagrid-body").triggerHandler("scroll");
        function fixSingleRowHeight(index, c1, c2) {

            c1 = c1 || view1;
            c2 = c2 || view2;
            var tr1 = c1.find("tr[datagrid-row-index=" + index + "]");
            var tr2 = c2.find("tr[datagrid-row-index=" + index + "]");
            tr1.css("height", "");
            tr2.css("height", "");
            var maxheight = Math.max(tr1.height(), tr2.height());
            tr1.css("height", maxheight);
            tr2.css("height", maxheight);
        }
        ;
    }
    ;
    function wrapGrid(target, rownumbers) {

        function getDataByCondition(conditons) {

            var trAry = [];
            $("tr", conditons).each(function () {

                var thAry = [];
                $("th", this).each(function () {

                    var th = $(this);
                    var col = {title: th.html(), align: th.attr("align") || "left", sortable: th.attr("sortable") == "true" || false, checkbox: th.attr("checkbox") == "true" || false};
                    if (th.attr("field")) {

                        col.field = th.attr("field");
                    }

                    if (th.attr("formatter")) {

                        col.formatter = eval(th.attr("formatter"));
                    }

                    if (th.attr("styler")) {

                        col.styler = eval(th.attr("styler"));
                    }

                    if (th.attr("editor")) {

                        var s = $.trim(th.attr("editor"));
                        if (s.substr(0, 1) == "{") {

                            col.editor = eval("(" + s + ")");
                        } else {

                            col.editor = s;
                        }

                    }

                    if (th.attr("rowspan")) {

                        col.rowspan = parseInt(th.attr("rowspan"));
                    }

                    if (th.attr("colspan")) {

                        col.colspan = parseInt(th.attr("colspan"));
                    }

                    if (th.attr("width")) {

                        col.width = parseInt(th.attr("width"));
                    }

                    if (th.attr("hidden")) {

                        col.hidden = th.attr("hidden") == "true";
                    }

                    if (th.attr("resizable")) {

                        col.resizable = th.attr("resizable") == "true";
                    }

                    thAry.push(col);
                });
                trAry.push(thAry);
            });
            return trAry;
        }
        ;
        var wrapObj = $("<div class=\"datagrid-wrap\">" + "<div class=\"datagrid-view\">" + "<div class=\"datagrid-view1\">" + "<div class=\"datagrid-header\">" + "<div class=\"datagrid-header-inner\"></div>" + "</div>" + "<div class=\"datagrid-body\">" + "<div class=\"datagrid-body-inner\"></div>" + "</div>" + "<div class=\"datagrid-footer\">" + "<div class=\"datagrid-footer-inner\"></div>" + "</div>" + "</div>" + "<div class=\"datagrid-view2\">" + "<div class=\"datagrid-header\">" + "<div class=\"datagrid-header-inner\"></div>" + "</div>" + "<div class=\"datagrid-body\"></div>" + "<div class=\"datagrid-footer\">" + "<div class=\"datagrid-footer-inner\"></div>" + "</div>" + "</div>" + "<div class=\"datagrid-resize-proxy\"></div>" + "</div>" + "</div>").insertAfter(target);
        wrapObj.panel({doSize: false});
        wrapObj.panel("panel").addClass("datagrid").bind("_resize", function (e, _32) {

            var opts = $.data(target, "datagrid").options;
            if (opts.fit == true || _32) {

                setSize(target);
                setTimeout(function () {

                    if ($.data(target, "datagrid")) {

                        fixColumnSize(target);
                    }

                }, 0);
            }

            return false;
        });
        $(target).hide().appendTo(wrapObj.children("div.datagrid-view"));
        var dataFrozen = getDataByCondition($("thead[frozen=true]", target));
        var dataFit = getDataByCondition($("thead[frozen!=true]", target));
        return {panel: wrapObj, frozenColumns: dataFrozen, columns: dataFit};
    }
    ;
    function getData(target) {

        var data = {total: 0, rows: []};
        var getCols = getColumnFiles(target, true).concat(getColumnFiles(target, false));
        $(target).find("tbody tr").each(function () {

            data.total++;
            var col = {};
            for (var i = 0; i < getCols.length; i++) {

                col[getCols[i]] = $("td:eq(" + i + ")", this).html();
            }

            data.rows.push(col);
        });
        return data;
    }
    ;
    function initDatagrid(target) {

        var opts = $.data(target, "datagrid").options;
        var pnl = $.data(target, "datagrid").panel;
        pnl.panel($.extend({}, opts, {doSize: false, onResize: function (_40, _41) {

                setTimeout(function () {

                    if ($.data(target, "datagrid")) {

                        fixDataGridHeight(target);
                        fixColumnWidth(target);
                        opts.onResize.call(pnl, _40, _41);
                    }

                }, 0);
            }, onExpand: function () {

                fixDataGridHeight(target);
                fixRowHeight(target);
                opts.onExpand.call(pnl);
            }

        }));
        var view = pnl.children("div.datagrid-view");
        var view1 = view.children("div.datagrid-view1");
        var view2 = view.children("div.datagrid-view2");
        var view1HeaderInner = view1.children("div.datagrid-header").children("div.datagrid-header-inner");
        var view2HeaderInner = view2.children("div.datagrid-header").children("div.datagrid-header-inner");
        appendColumns(view1HeaderInner, opts.frozenColumns, true);
        appendColumns(view2HeaderInner, opts.columns, false);
        view1HeaderInner.css("display", opts.showHeader ? "block" : "none");
        view2HeaderInner.css("display", opts.showHeader ? "block" : "none");
        view1.find("div.datagrid-footer-inner").css("display", opts.showFooter ? "block" : "none");
        view2.find("div.datagrid-footer-inner").css("display", opts.showFooter ? "block" : "none");
        if (opts.toolbar) {

            if (typeof opts.toolbar == "string") {

                $(opts.toolbar).addClass("datagrid-toolbar").prependTo(pnl);
                $(opts.toolbar).show();
            } else {

                $("div.datagrid-toolbar", pnl).remove();
                var tb = $("<div class=\"datagrid-toolbar\"></div>").prependTo(pnl);
                for (var i = 0; i < opts.toolbar.length; i++) {

                    var btn = opts.toolbar[i];
                    if (btn == "-") {

                        $("<div class=\"datagrid-btn-separator\"></div>").appendTo(tb);
                    } else {

                        var lnkObj = $("<a href=\"javascript:void(0)\"></a>");
                        lnkObj[0].onclick = eval(btn.handler || function () {

                        });
                        lnkObj.css("float", "left").appendTo(tb).linkbutton($.extend({}, btn, {plain: true}));
                    }

                }

            }

        } else {

            $("div.datagrid-toolbar", pnl).remove();
        }

        $("div.datagrid-pager", pnl).remove();
        if (opts.pagination) {

            var pagerObj = $("<div class=\"datagrid-pager\"></div>").appendTo(pnl);
            pagerObj.pagination({pageNumber: opts.pageNumber, pageSize: opts.pageSize, pageList: opts.pageList, onSelectPage: function (_4a, _4b) {

                    opts.pageNumber = _4a;
                    opts.pageSize = _4b;
                    request(target);
                }

            });
            opts.pageSize = pagerObj.pagination("options").pageSize;
        }

        function appendColumns(container, columns, frozen) {

            if (!columns) {

                return;
            }

            $(container).show();
            $(container).empty();
            var t = $("<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody></tbody></table>").appendTo(container);
            for (var i = 0; i < columns.length; i++) {

                var tr = $("<tr></tr>").appendTo($("tbody", t));
                var findCol = columns[i];
                for (var j = 0; j < findCol.length; j++) {

                    var col = findCol[j];
                    var strHtml = "";
                    if (col.rowspan) {

                        strHtml += "rowspan=\"" + col.rowspan + "\" ";
                    }

                    if (col.colspan) {

                        strHtml += "colspan=\"" + col.colspan + "\" ";
                    }

                    var td = $("<td " + strHtml + "></td>").appendTo(tr);
                    if (col.checkbox) {

                        td.attr("field", col.field);
                        $("<div class=\"datagrid-header-check\"></div>").html("<input type=\"checkbox\"/>").appendTo(td);
                    } else {

                        if (col.field) {

                            td.attr("field", col.field);
                            td.append("<div class=\"datagrid-cell\"><span></span><span class=\"datagrid-sort-icon\"></span></div>");
                            $("span", td).html(col.title);
                            $("span.datagrid-sort-icon", td).html("");
                            var findCell = td.find("div.datagrid-cell");
                            if (col.resizable == false) {

                                findCell.attr("resizable", "false");
                            }

                            col.boxWidth = $.boxModel ? (col.width - (findCell.outerWidth() - findCell.width())) : col.width;
                            findCell.width(col.boxWidth);
                            findCell.css("text-align", (col.align || "left"));
                        } else {

                            $("<div class=\"datagrid-cell-group\"></div>").html(col.title).appendTo(td);
                        }

                    }

                    if (col.hidden) {

                        td.hide();
                    }

                }

            }

            if (frozen && opts.rownumbers) {

                var td = $("<td rowspan=\"" + opts.frozenColumns.length + "\"><div class=\"datagrid-header-rownumber\"></div></td>");
                if ($("tr", t).length == 0) {

                    td.wrap("<tr></tr>").parent().appendTo($("tbody", t));
                } else {

                    td.prependTo($("tr:first", t));
                }

            }

        }
        ;
    }
    ;
    function bindRowEvents(target) {

        var pnl = $.data(target, "datagrid").panel;
        var opts = $.data(target, "datagrid").options;
        var data = $.data(target, "datagrid").data;
        var pnlBody = pnl.find("div.datagrid-body");
        pnlBody.find("tr[datagrid-row-index]").unbind(".datagrid").bind("mouseenter.datagrid", function () {

            var findIndex = $(this).attr("datagrid-row-index");
            pnlBody.find("tr[datagrid-row-index=" + findIndex + "]").addClass("datagrid-row-over");
        }).bind("mouseleave.datagrid", function () {

            var findIndex = $(this).attr("datagrid-row-index");
            pnlBody.find("tr[datagrid-row-index=" + findIndex + "]").removeClass("datagrid-row-over");
        }).bind("click.datagrid", function () {

            var findIndex = $(this).attr("datagrid-row-index");
            if (opts.singleSelect == true) {

                clearSelections(target);
                selectRow(target, findIndex);
            } else {

                if ($(this).hasClass("datagrid-row-selected")) {

                    unSelectRow(target, findIndex);
                } else {

                    selectRow(target, findIndex);
                }

            }

            if (opts.onClickRow) {

                opts.onClickRow.call(target, findIndex, data.rows[findIndex]);
            }

        }).bind("dblclick.datagrid", function () {

            var findIndex = $(this).attr("datagrid-row-index");
            if (opts.onDblClickRow) {

                opts.onDblClickRow.call(target, findIndex, data.rows[findIndex]);
            }

        }).bind("contextmenu.datagrid", function (e) {

            var findIndex = $(this).attr("datagrid-row-index");
            if (opts.onRowContextMenu) {

                opts.onRowContextMenu.call(target, e, findIndex, data.rows[findIndex]);
            }

        });
        pnlBody.find("td[field]").unbind(".datagrid").bind("click.datagrid", function () {

            var findIndex = $(this).parent().attr("datagrid-row-index");
            var findField = $(this).attr("field");
            var findRow = data.rows[findIndex][findField];
            opts.onClickCell.call(target, findIndex, findField, findRow);
        }).bind("dblclick.datagrid", function () {

            var findIndex = $(this).parent().attr("datagrid-row-index");
            var findField = $(this).attr("field");
            var findRow = data.rows[findIndex][findField];
            opts.onDblClickCell.call(target, findIndex, findField, findRow);
        });
        pnlBody.find("div.datagrid-cell-check input[type=checkbox]").unbind(".datagrid").bind("click.datagrid", function (e) {

            var findIndex = $(this).parent().parent().parent().attr("datagrid-row-index");
            if (opts.singleSelect) {

                clearSelections(target);
                selectRow(target, findIndex);
            } else {

                if ($(this).attr("checked")) {

                    selectRow(target, findIndex);
                } else {

                    unSelectRow(target, findIndex);
                }

            }

            e.stopPropagation();
        });
    }
    ;
    function bindCellsEvents(target) {

        var pnl = $.data(target, "datagrid").panel;
        var opts = $.data(target, "datagrid").options;
        var pnlHeader = pnl.find("div.datagrid-header");
        pnlHeader.find("td:has(div.datagrid-cell)").unbind(".datagrid").bind("mouseenter.datagrid", function () {

            $(this).addClass("datagrid-header-over");
        }).bind("mouseleave.datagrid", function () {

            $(this).removeClass("datagrid-header-over");
        }).bind("contextmenu.datagrid", function (e) {

            var filed = $(this).attr("field");
            opts.onHeaderContextMenu.call(target, e, filed);
        });
        pnlHeader.find("div.datagrid-cell").unbind(".datagrid").bind("click.datagrid", function () {

            var filed = $(this).parent().attr("field");
            var opt = getColOptions(target, filed);
            if (!opt.sortable) {

                return;
            }

            opts.sortName = filed;
            opts.sortOrder = "asc";
            var c = "datagrid-sort-asc";
            if ($(this).hasClass("datagrid-sort-asc")) {

                c = "datagrid-sort-desc";
                opts.sortOrder = "desc";
            }

            pnlHeader.find("div.datagrid-cell").removeClass("datagrid-sort-asc datagrid-sort-desc");
            $(this).addClass(c);
            if (opts.onSortColumn) {

                opts.onSortColumn.call(target, opts.sortName, opts.sortOrder);
            }

            if (opts.remoteSort) {

                request(target);
            } else {

                var data = $.data(target, "datagrid").data;
                loadData(target, data);
            }

        });
        pnlHeader.find("input[type=checkbox]").unbind(".datagrid").bind("click.datagrid", function () {

            if (opts.singleSelect) {

                return false;
            }

            if ($(this).attr("checked")) {

                selectAll(target);
            } else {

                clearSelectRows(target);
            }

        });
        var view = pnl.children("div.datagrid-view");
        var view1 = view.children("div.datagrid-view1");
        var view2 = view.children("div.datagrid-view2");
        view2.children("div.datagrid-body").unbind(".datagrid").bind("scroll.datagrid", function () {

            view1.children("div.datagrid-body").scrollTop($(this).scrollTop());
            view2.children("div.datagrid-header").scrollLeft($(this).scrollLeft());
            view2.children("div.datagrid-footer").scrollLeft($(this).scrollLeft());
        });
        pnlHeader.find("div.datagrid-cell").each(function () {

            $(this).resizable({handles: "e", disabled: ($(this).attr("resizable") ? $(this).attr("resizable") == "false" : false), minWidth: 25, onStartResize: function (e) {

                    view.children("div.datagrid-resize-proxy").css({left: e.pageX - $(pnl).offset().left - 1, display: "block"});
                }, onResize: function (e) {

                    view.children("div.datagrid-resize-proxy").css({display: "block", left: e.pageX - $(pnl).offset().left - 1});
                    return false;
                }, onStopResize: function (e) {

                    var field = $(this).parent().attr("field");
                    var col = getColOptions(target, field);
                    col.width = $(this).outerWidth();
                    col.boxWidth = $.boxModel == true ? $(this).width() : $(this).outerWidth();
                    fixColumnSize(target, field);
                    fixColumnWidth(target);
                    var tmpView = pnl.find("div.datagrid-view2");
                    tmpView.find("div.datagrid-header").scrollLeft(tmpView.find("div.datagrid-body").scrollLeft());
                    view.children("div.datagrid-resize-proxy").css("display", "none");
                    opts.onResizeColumn.call(target, field, col.width);
                }

            });
        });
        view1.children("div.datagrid-header").find("div.datagrid-cell").resizable({onStopResize: function (e) {

                var filed = $(this).parent().attr("field");
                var col = getColOptions(target, filed);
                col.width = $(this).outerWidth();
                col.boxWidth = $.boxModel == true ? $(this).width() : $(this).outerWidth();
                fixColumnSize(target, filed);
                var tmpView = pnl.find("div.datagrid-view2");
                tmpView.find("div.datagrid-header").scrollLeft(tmpView.find("div.datagrid-body").scrollLeft());
                view.children("div.datagrid-resize-proxy").css("display", "none");
                fixDataGridHeight(target);
                fixColumnWidth(target);
                opts.onResizeColumn.call(target, filed, col.width);
            }

        });
    }
    ;
    function fixColumnWidth(target) {

        var opts = $.data(target, "datagrid").options;
        if (!opts.fitColumns) {

            return;
        }

        var pnl = $.data(target, "datagrid").panel;
        var view2Header = pnl.find("div.datagrid-view2 div.datagrid-header");
        var tmpWidth = 0;
        var tmpCol;
        var coFields = getColumnFiles(target, false);
        for (var i = 0; i < coFields.length; i++) {

            var col = getColOptions(target, coFields[i]);
            if (!col.hidden && !col.checkbox) {

                tmpWidth += col.width;
                tmpCol = col;
            }

        }

        var view2HeaderInner = view2Header.children("div.datagrid-header-inner").show();
        var jzWidth = view2Header.width() - view2Header.find("table").width() - opts.scrollbarSize;
        var ratio = jzWidth / tmpWidth;
        if (!opts.showHeader) {

            view2HeaderInner.hide();
        }

        for (var i = 0; i < coFields.length; i++) {

            var col = getColOptions(target, coFields[i]);
            if (!col.hidden && !col.checkbox) {

                var floorWidth = Math.floor(col.width * ratio);
                setCellWidth(col, floorWidth);
                jzWidth -= floorWidth;
            }

        }

        fixColumnSize(target);
        if (jzWidth) {

            setCellWidth(tmpCol, jzWidth);
            fixColumnSize(target, tmpCol.field);
        }

        function setCellWidth(col, width) {

            col.width += width;
            col.boxWidth += width;
            view2Header.find("td[field=" + col.field + "] div.datagrid-cell").width(col.boxWidth);
        }
        ;
    }
    ;
    function fixColumnSize(target, filed) {

        var pnl = $.data(target, "datagrid").panel;
        var bf = pnl.find("div.datagrid-body,div.datagrid-footer");
        if (filed) {

            fix(filed);
        } else {

            pnl.find("div.datagrid-header td[field]").each(function () {

                fix($(this).attr("field"));
            });
        }

        fixCellWidth(target);
        setTimeout(function () {

            fixRowHeight(target);
            resizeEditor(target);
        }, 0);
        function fix(_88) {

            var col = getColOptions(target, _88);
            bf.find("td[field=" + _88 + "]").each(function () {

                var td = $(this);
                var _89 = td.attr("colspan") || 1;
                if (_89 == 1) {

                    td.find("div.datagrid-cell").width(col.boxWidth);
                    td.find("div.datagrid-editable").width(col.width);
                }

            });
        }
        ;
    }
    ;
    function fixCellWidth(target) {

        var pnl = $.data(target, "datagrid").panel;
        var pnlHeader = pnl.find("div.datagrid-header");
        pnl.find("div.datagrid-body td.datagrid-td-merged").each(function () {

            var td = $(this);
            var colspan = td.attr("colspan") || 1;
            var tdFiled = td.attr("field");
            var findTd = pnlHeader.find("td[field=" + tdFiled + "]");
            var findTdWidth = findTd.width();
            for (var i = 1; i < colspan; i++) {

                findTd = findTd.next();
                findTdWidth += findTd.outerWidth();
            }

            var tdCell = td.children("div.datagrid-cell");
            if ($.boxModel == true) {

                tdCell.width(findTdWidth - (tdCell.outerWidth() - tdCell.width()));
            } else {

                tdCell.width(findTdWidth);
            }

        });
    }
    ;
    function resizeEditor(target) {

        var pnl = $.data(target, "datagrid").panel;
        pnl.find("div.datagrid-editable").each(function () {

            var ed = $.data(this, "datagrid.editor");
            if (ed.actions.resize) {

                ed.actions.resize(ed.target, $(this).width());
            }

        });
    }
    ;
    function getColOptions(target, filed) {

        var opts = $.data(target, "datagrid").options;
        if (opts.columns) {

            for (var i = 0; i < opts.columns.length; i++) {

                var colOpts = opts.columns[i];
                for (var j = 0; j < colOpts.length; j++) {

                    var col = colOpts[j];
                    if (col.field == filed) {

                        return col;
                    }

                }

            }

        }

        if (opts.frozenColumns) {

            for (var i = 0; i < opts.frozenColumns.length; i++) {

                var colOpts = opts.frozenColumns[i];
                for (var j = 0; j < colOpts.length; j++) {

                    var col = colOpts[j];
                    if (col.field == filed) {

                        return col;
                    }

                }

            }

        }

        return null;
    }
    ;
    function getColumnFiles(target, frozen) {

        var opts = $.data(target, "datagrid").options;
        var cols = (frozen == true) ? (opts.frozenColumns || [[]]) : opts.columns;
        if (cols.length == 0) {

            return [];
        }

        var columnFileds = [];
        function findLocation(index) {

            var c = 0;
            var i = 0;
            while (true) {

                if (columnFileds[i] == undefined) {

                    if (c == index) {

                        return i;
                    }

                    c++;
                }

                i++;
            }

        }
        ;
        function getColumnFileds(r) {

            var ff = [];
            var c = 0;
            for (var i = 0; i < cols[r].length; i++) {

                var col = cols[r][i];
                if (col.field) {

                    ff.push([c, col.field]);
                }

                c += parseInt(col.colspan || "1");
            }

            for (var i = 0; i < ff.length; i++) {

                ff[i][0] = findLocation(ff[i][0]);
            }

            for (var i = 0; i < ff.length; i++) {

                var f = ff[i];
                columnFileds[f[0]] = f[1];
            }

        }
        ;
        for (var i = 0; i < cols.length; i++) {

            getColumnFileds(i);
        }

        return columnFileds;
    }
    ;
    function loadData(target, data) {

        var opts = $.data(target, "datagrid").options;
        var pnl = $.data(target, "datagrid").panel;
        var selectRows = $.data(target, "datagrid").selectedRows;
        data = opts.loadFilter.call(target, data);
        var dataRows = data.rows;
        $.data(target, "datagrid").data = data;
        if (data.footer) {

            $.data(target, "datagrid").footer = data.footer;
        }

        if (!opts.remoteSort) {

            var opt = getColOptions(target, opts.sortName);
            if (opt) {

                var _a9 = opt.sorter || function (a, b) {

                    return (a > b ? 1 : -1);
                };
                data.rows.sort(function (r1, r2) {

                    return _a9(r1[opts.sortName], r2[opts.sortName]) * (opts.sortOrder == "asc" ? 1 : -1);
                });
            }

        }

        var view = pnl.children("div.datagrid-view");
        var view1 = view.children("div.datagrid-view1");
        var view2 = view.children("div.datagrid-view2");
        if (opts.view.onBeforeRender) {

            opts.view.onBeforeRender.call(opts.view, target, dataRows);
        }

        opts.view.render.call(opts.view, target, view2.children("div.datagrid-body"), false);
        opts.view.render.call(opts.view, target, view1.children("div.datagrid-body").children("div.datagrid-body-inner"), true);
        if (opts.showFooter) {

            opts.view.renderFooter.call(opts.view, target, view2.find("div.datagrid-footer-inner"), false);
            opts.view.renderFooter.call(opts.view, target, view1.find("div.datagrid-footer-inner"), true);
        }

        if (opts.view.onAfterRender) {

            opts.view.onAfterRender.call(opts.view, target);
        }

        opts.onLoadSuccess.call(target, data);
        var pager = pnl.children("div.datagrid-pager");
        if (pager.length) {

            if (pager.pagination("options").total != data.total) {

                pager.pagination({total: data.total});
            }

        }

        fixRowHeight(target);
        bindRowEvents(target);
        view2.children("div.datagrid-body").triggerHandler("scroll");
        if (opts.idField) {

            for (var i = 0; i < dataRows.length; i++) {

                if (isExistsRow(dataRows[i])) {

                    selectRecord(target, dataRows[i][opts.idField]);
                }

            }

        }

        function isExistsRow(row) {

            for (var i = 0; i < selectRows.length; i++) {

                if (selectRows[i][opts.idField] == row[opts.idField]) {

                    selectRows[i] = row;
                    return true;
                }

            }

            return false;
        }
        ;
    }
    ;
    function getRowIndex(target, row) {

        var opts = $.data(target, "datagrid").options;
        var rows = $.data(target, "datagrid").data.rows;
        if (typeof row == "object") {

            return rows.indexOf(row);
        } else {

            for (var i = 0; i < rows.length; i++) {

                if (rows[i][opts.idField] == row) {

                    return i;
                }

            }

            return -1;
        }

    }
    ;
    function getSelectRows(target) {

        var opts = $.data(target, "datagrid").options;
        var pnl = $.data(target, "datagrid").panel;
        var data = $.data(target, "datagrid").data;
        if (opts.idField) {

            return $.data(target, "datagrid").selectedRows;
        } else {

            var rowAry = [];
            $("div.datagrid-view2 div.datagrid-body tr.datagrid-row-selected", pnl).each(function () {

                var row_index = parseInt($(this).attr("datagrid-row-index"));
                rowAry.push(data.rows[row_index]);
            });
            return rowAry;
        }

    }
    ;
    function clearSelections(target) {

        clearSelectRows(target);
        var selectRows = $.data(target, "datagrid").selectedRows;
        selectRows.splice(0, selectRows.length);
    }
    ;
    function selectAll(target) {

        var opts = $.data(target, "datagrid").options;
        var pnl = $.data(target, "datagrid").panel;
        var data = $.data(target, "datagrid").data;
        var selectRows = $.data(target, "datagrid").selectedRows;
        var rows = data.rows;
        var pnlBody = pnl.find("div.datagrid-body");
        $("tr", pnlBody).addClass("datagrid-row-selected");
        $("div.datagrid-cell-check input[type=checkbox]", pnlBody).attr("checked", true);
        for (var i = 0; i < rows.length; i++) {

            if (opts.idField) {

                (function () {

                    var row = rows[i];
                    for (var i = 0; i < selectRows.length; i++) {

                        if (selectRows[i][opts.idField] == row[opts.idField]) {

                            return;
                        }

                    }

                    selectRows.push(row);
                })();
            }

        }

        opts.onSelectAll.call(target, rows);
    }
    ;
    function clearSelectRows(target) {

        var opts = $.data(target, "datagrid").options;
        var pnl = $.data(target, "datagrid").panel;
        var data = $.data(target, "datagrid").data;
        var selectRows = $.data(target, "datagrid").selectedRows;
        $("div.datagrid-body tr.datagrid-row-selected", pnl).removeClass("datagrid-row-selected");
        $("div.datagrid-body div.datagrid-cell-check input[type=checkbox]", pnl).attr("checked", false);
        if (opts.idField) {

            for (var i = 0; i < data.rows.length; i++) {

                selectRows.removeById(opts.idField, data.rows[i][opts.idField]);
            }

        }

        opts.onUnselectAll.call(target, data.rows);
    }
    ;
    function selectRow(target, index) {

        var pnl = $.data(target, "datagrid").panel;
        var opts = $.data(target, "datagrid").options;
        var data = $.data(target, "datagrid").data;
        var selectRows = $.data(target, "datagrid").selectedRows;
        if (index < 0 || index >= data.rows.length) {

            return;
        }

        if (opts.singleSelect == true) {

            clearSelections(target);
        }

        var tr = $("div.datagrid-body tr[datagrid-row-index=" + index + "]", pnl);
        if (!tr.hasClass("datagrid-row-selected")) {

            tr.addClass("datagrid-row-selected");
            var ck = $("div.datagrid-cell-check input[type=checkbox]", tr);
            ck.attr("checked", true);
            if (opts.idField) {

                var row = data.rows[index];
                (function () {

                    for (var i = 0; i < selectRows.length; i++) {

                        if (selectRows[i][opts.idField] == row[opts.idField]) {

                            return;
                        }

                    }

                    selectRows.push(row);
                })();
            }

        }

        opts.onSelect.call(target, index, data.rows[index]);
        var view2 = pnl.find("div.datagrid-view2");
        var view2Header = view2.find("div.datagrid-header").outerHeight();
        var view2Body = view2.find("div.datagrid-body");
        var top = tr.position().top - view2Header;
        if (top <= 0) {

            view2Body.scrollTop(view2Body.scrollTop() + top);
        } else {

            if (top + tr.outerHeight() > view2Body.height() - 18) {

                view2Body.scrollTop(view2Body.scrollTop() + top + tr.outerHeight() - view2Body.height() + 18);
            }

        }

    }
    ;
    function selectRecord(target, id) {

        var opts = $.data(target, "datagrid").options;
        var data = $.data(target, "datagrid").data;
        if (opts.idField) {

            var _da = -1;
            for (var i = 0; i < data.rows.length; i++) {

                if (data.rows[i][opts.idField] == id) {

                    _da = i;
                    break;
                }

            }

            if (_da >= 0) {

                selectRow(target, _da);
            }

        }

    }
    ;
    function unSelectRow(target, index) {

        var opts = $.data(target, "datagrid").options;
        var pnl = $.data(target, "datagrid").panel;
        var data = $.data(target, "datagrid").data;
        var selectRows = $.data(target, "datagrid").selectedRows;
        if (index < 0 || index >= data.rows.length) {

            return;
        }

        var pnlBody = pnl.find("div.datagrid-body");
        var tr = $("tr[datagrid-row-index=" + index + "]", pnlBody);
        var ck = $("tr[datagrid-row-index=" + index + "] div.datagrid-cell-check input[type=checkbox]", pnlBody);
        tr.removeClass("datagrid-row-selected");
        ck.attr("checked", false);
        var row = data.rows[index];
        if (opts.idField) {

            selectRows.removeById(opts.idField, row[opts.idField]);
        }

        opts.onUnselect.call(target, index, row);
    }
    ;
    function beginEdit(target, index) {

        var opts = $.data(target, "datagrid").options;
        var tr = opts.editConfig.getTr(target, index);
        var row = opts.editConfig.getRow(target, index);
        if (tr.hasClass("datagrid-row-editing")) {

            return;
        }

        if (opts.onBeforeEdit.call(target, index, row) == false) {

            return;
        }

        tr.addClass("datagrid-row-editing");
        beginEditRow(target, index);
        resizeEditor(target);
        tr.find("div.datagrid-editable").each(function () {

            var filed = $(this).parent().attr("field");
            var ed = $.data(this, "datagrid.editor");
            ed.actions.setValue(ed.target, row[filed]);
        });
        validateRow(target, index);
    }
    ;
    function endEdit(target, index, canceld) {

        var opts = $.data(target, "datagrid").options;
        var updatedRows = $.data(target, "datagrid").updatedRows;
        var insertedRows = $.data(target, "datagrid").insertedRows;
        var tr = opts.editConfig.getTr(target, index);
        var row = opts.editConfig.getRow(target, index);
        if (!tr.hasClass("datagrid-row-editing")) {

            return;
        }

        if (!canceld) {

            if (!validateRow(target, index)) {

                return;
            }

            var flag = false;
            var tmpObj = {};
            tr.find("div.datagrid-editable").each(function () {

                var field = $(this).parent().attr("field");
                var ed = $.data(this, "datagrid.editor");
                var editValue = ed.actions.getValue(ed.target);
                if (row[field] != editValue) {

                    row[field] = editValue;
                    flag = true;
                    tmpObj[field] = editValue;
                }

            });
            if (flag) {

                if (insertedRows.indexOf(row) == -1) {

                    if (updatedRows.indexOf(row) == -1) {

                        updatedRows.push(row);
                    }

                }

            }

        }

        tr.removeClass("datagrid-row-editing");
        destroyEdit(target, index);
        $(target).datagrid("refreshRow", index);
        if (!canceld) {

            opts.onAfterEdit.call(target, index, row, tmpObj);
        } else {

            opts.onCancelEdit.call(target, index, row);
        }

    }
    ;
    function getEditors(target, index) {

        var editors = [];
        var pnl = $.data(target, "datagrid").panel;
        var tr = $("div.datagrid-body tr[datagrid-row-index=" + index + "]", pnl);
        tr.children("td").each(function () {

            var editorsAry = $(this).find("div.datagrid-editable");
            if (editorsAry.length) {

                var ed = $.data(editorsAry[0], "datagrid.editor");
                editors.push(ed);
            }

        });
        return editors;
    }
    ;
    function getEditor(target, options) {

        var editors = getEditors(target, options.index);
        for (var i = 0; i < editors.length; i++) {

            if (editors[i].field == options.field) {

                return editors[i];
            }

        }

        return null;
    }
    ;
    function beginEditRow(target, index) {

        var opts = $.data(target, "datagrid").options;
        var tr = opts.editConfig.getTr(target, index);
        tr.children("td").each(function () {

            var cell = $(this).find("div.datagrid-cell");
            var thisFiled = $(this).attr("field");
            var col = getColOptions(target, thisFiled);
            if (col && col.editor) {

                var tmpType, tmpOpts;
                if (typeof col.editor == "string") {

                    tmpType = col.editor;
                } else {

                    tmpType = col.editor.type;
                    tmpOpts = col.editor.options;
                }

                var tmpEditor = opts.editors[tmpType];
                if (tmpEditor) {

                    var cellHtml = cell.html();
                    var cellOutWidth = cell.outerWidth();
                    cell.addClass("datagrid-editable");
                    if ($.boxModel == true) {

                        cell.width(cellOutWidth - (cell.outerWidth() - cell.width()));
                    }

                    cell.html("<table border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tr><td></td></tr></table>");
                    cell.children("table").attr("align", col.align);
                    cell.children("table").bind("click dblclick contextmenu", function (e) {

                        e.stopPropagation();
                    });
                    $.data(cell[0], "datagrid.editor", {actions: tmpEditor, target: tmpEditor.init(cell.find("td"), tmpOpts), field: thisFiled, type: tmpType, oldHtml: cellHtml});
                }

            }

        });
        fixRowHeight(target, index);
    }
    ;
    function destroyEdit(target, index) {

        var opts = $.data(target, "datagrid").options;
        var tr = opts.editConfig.getTr(target, index);
        tr.children("td").each(function () {

            var cell = $(this).find("div.datagrid-editable");
            if (cell.length) {

                var ed = $.data(cell[0], "datagrid.editor");
                if (ed.actions.destroy) {

                    ed.actions.destroy(ed.target);
                }

                cell.html(ed.oldHtml);
                $.removeData(cell[0], "datagrid.editor");
                var cellOuterWidth = cell.outerWidth();
                cell.removeClass("datagrid-editable");
                if ($.boxModel == true) {

                    cell.width(cellOuterWidth - (cell.outerWidth() - cell.width()));
                }

            }

        });
    }
    ;
    function validateRow(target, index) {

        var tr = $.data(target, "datagrid").options.editConfig.getTr(target, index);
        if (!tr.hasClass("datagrid-row-editing")) {

            return true;
        }

        var vbox = tr.find(".validatebox-text");
        vbox.validatebox("validate");
        vbox.trigger("mouseleave");
        var trAry = tr.find(".validatebox-invalid");
        return trAry.length == 0;
    }
    ;
    function getChanges(target, type) {

        var insertedRows = $.data(target, "datagrid").insertedRows;
        var deletedRows = $.data(target, "datagrid").deletedRows;
        var updatedRows = $.data(target, "datagrid").updatedRows;
        if (!type) {

            var rows = [];
            rows = rows.concat(insertedRows);
            rows = rows.concat(deletedRows);
            rows = rows.concat(updatedRows);
            return rows;
        } else {

            if (type == "inserted") {

                return insertedRows;
            } else {

                if (type == "deleted") {

                    return deletedRows;
                } else {

                    if (type == "updated") {

                        return updatedRows;
                    }

                }

            }

        }

        return [];
    }
    ;
    function deleteRow(taget, index) {

        var opts = $.data(taget, "datagrid").options;
        var data = $.data(taget, "datagrid").data;
        var insertedRows = $.data(taget, "datagrid").insertedRows;
        var deletedRows = $.data(taget, "datagrid").deletedRows;
        var selectedRows = $.data(taget, "datagrid").selectedRows;
        $(taget).datagrid("cancelEdit", index);
        var row = data.rows[index];
        if (insertedRows.indexOf(row) >= 0) {

            insertedRows.remove(row);
        } else {

            deletedRows.push(row);
        }

        selectedRows.removeById(opts.idField, data.rows[index][opts.idField]);
        opts.view.deleteRow.call(opts.view, taget, index);
        if (opts.height == "auto") {

            fixRowHeight(taget);
        }

    }
    ;
    function insertRow(target, param) {

        var view = $.data(target, "datagrid").options.view;
        var insertedRows = $.data(target, "datagrid").insertedRows;
        view.insertRow.call(view, target, param.index, param.row);
        bindRowEvents(target);
        insertedRows.push(param.row);
    }
    ;
    function appendRow(target, row) {

        var view = $.data(target, "datagrid").options.view;
        var insertedRows = $.data(target, "datagrid").insertedRows;
        view.insertRow.call(view, target, null, row);
        bindRowEvents(target);
        insertedRows.push(row);
    }
    ;
    function cacheRows(target) {

        var data = $.data(target, "datagrid").data;
        var rows = data.rows;
        var rowAry = [];
        for (var i = 0; i < rows.length; i++) {

            rowAry.push($.extend({}, rows[i]));
        }

        $.data(target, "datagrid").originalRows = rowAry;
        $.data(target, "datagrid").updatedRows = [];
        $.data(target, "datagrid").insertedRows = [];
        $.data(target, "datagrid").deletedRows = [];
    }
    ;
    function acceptChanges(target) {

        var data = $.data(target, "datagrid").data;
        var ok = true;
        for (var i = 0, len = data.rows.length; i < len; i++) {

            if (validateRow(target, i)) {

                endEdit(target, i, false);
            } else {

                ok = false;
            }

        }

        if (ok) {

            cacheRows(target);
        }

    }
    ;
    function rejectChanges(target) {

        var opts = $.data(target, "datagrid").options;
        var originalRows = $.data(target, "datagrid").originalRows;
        var insertedRows = $.data(target, "datagrid").insertedRows;
        var deletedRows = $.data(target, "datagrid").deletedRows;
        var selectedRows = $.data(target, "datagrid").selectedRows;
        var data = $.data(target, "datagrid").data;
        for (var i = 0; i < data.rows.length; i++) {

            endEdit(target, i, true);
        }

        var rowAry = [];
        for (var i = 0; i < selectedRows.length; i++) {

            rowAry.push(selectedRows[i][opts.idField]);
        }

        selectedRows.splice(0, selectedRows.length);
        data.total += deletedRows.length - insertedRows.length;
        data.rows = originalRows;
        loadData(target, data);
        for (var i = 0; i < rowAry.length; i++) {

            selectRecord(target, rowAry[i]);
        }

        cacheRows(target);
    }
    ;
    function request(target, param) {

        var pnl = $.data(target, "datagrid").panel;
        var opts = $.data(target, "datagrid").options;
        if (param) {

            opts.queryParams = param;
        }

        if (!opts.url) {

            return;
        }

        var queryObj = $.extend({}, opts.queryParams);
        if (opts.pagination) {

            $.extend(queryObj, {page: opts.pageNumber, rows: opts.pageSize});
        }

        if (opts.sortName) {

            $.extend(queryObj, {sort: opts.sortName, order: opts.sortOrder});
        }

        if (opts.onBeforeLoad.call(target, queryObj) == false) {

            return;
        }

        $(target).datagrid("loading");
        setTimeout(function () {

            ajaxRequest();
        }, 0);
        function ajaxRequest() {

            $.ajax({type: opts.method, url: opts.url, data: queryObj, dataType: "json", success: function (data) {

                    setTimeout(function () {

                        $(target).datagrid("loaded");
                    }, 0);
                    parent.closeLoadinglayer();
                    loadData(target, data);
                    setTimeout(function () {

                        cacheRows(target);
                    }, 0);
                }, error: function () {

                    setTimeout(function () {

                        $(target).datagrid("loaded");
                    }, 0);
                    if (opts.onLoadError) {

                        opts.onLoadError.apply(target, arguments);
                    }

                }

            });
        }
        ;
    }
    ;
    function mergeCells(target, options) {

        var rows = $.data(target, "datagrid").data.rows;
        var pnl = $.data(target, "datagrid").panel;
        options.rowspan = options.rowspan || 1;
        options.colspan = options.colspan || 1;
        if (options.index < 0 || options.index >= rows.length) {

            return;
        }

        if (options.rowspan == 1 && options.colspan == 1) {

            return;
        }

        var findRow = rows[options.index][options.field];
        var tr = pnl.find("div.datagrid-body tr[datagrid-row-index=" + options.index + "]");
        var td = tr.find("td[field=" + options.field + "]");
        td.attr("rowspan", options.rowspan).attr("colspan", options.colspan);
        td.addClass("datagrid-td-merged");
        for (var i = 1; i < options.colspan; i++) {

            td = td.next();
            td.hide();
            rows[options.index][td.attr("field")] = findRow;
        }

        for (var i = 1; i < options.rowspan; i++) {

            tr = tr.next();
            var td = tr.find("td[field=" + options.field + "]").hide();
            rows[options.index + i][td.attr("field")] = findRow;
            for (var j = 1; j < options.colspan; j++) {

                td = td.next();
                td.hide();
                rows[options.index + i][td.attr("field")] = findRow;
            }

        }

        setTimeout(function () {

            fixCellWidth(target);
        }, 0);
    }
    ;
    $.fn.datagrid = function (options, param) {

        if (typeof options == "string") {

            return $.fn.datagrid.methods[options](this, param);
        }

        options = options || {};
        return this.each(function () {

            var state = $.data(this, "datagrid");
            var opts;
            if (state) {

                opts = $.extend(state.options, options);
                state.options = opts;
            } else {

                opts = $.extend({}, $.fn.datagrid.defaults, $.fn.datagrid.parseOptions(this), options);
                $(this).css("width", "").css("height", "");
                var wrapResult = wrapGrid(this, opts.rownumbers);
                if (!opts.columns) {

                    opts.columns = wrapResult.columns;
                }

                if (!opts.frozenColumns) {

                    opts.frozenColumns = wrapResult.frozenColumns;
                }

                $.data(this, "datagrid", {options: opts, panel: wrapResult.panel, selectedRows: [], data: {total: 0, rows: []}, originalRows: [], updatedRows: [], insertedRows: [], deletedRows: []});
            }

            initDatagrid(this);
            if (!state) {

                var data = getData(this);
                if (data.total > 0) {

                    loadData(this, data);
                    cacheRows(this);
                }

            }

            setSize(this);
            if (opts.url) {

                request(this);
            }

            bindCellsEvents(this);
        });
    };
    var editorsObj = {text: {init: function (container, options) {

                var gridInput = $("<input type=\"text\" class=\"datagrid-editable-input\">").appendTo(container);
                return gridInput;
            }, getValue: function (target) {

                return $(target).val();
            }, setValue: function (target, value) {

                $(target).val(value);
            }, resize: function (target, width) {

                var targetObj = $(target);
                if ($.boxModel == true) {

                    targetObj.width(width - (targetObj.outerWidth() - targetObj.width()));
                } else {

                    targetObj.width(width);
                }

            }

        }, textarea: {init: function (container, options) {

                var gridTextAreaObj = $("<textarea class=\"datagrid-editable-input\"></textarea>").appendTo(container);
                return gridTextAreaObj;
            }, getValue: function (target) {

                return $(target).val();
            }, setValue: function (target, value) {

                $(target).val(value);
            }, resize: function (target, width) {

                var targetObj = $(target);
                if ($.boxModel == true) {

                    targetObj.width(width - (targetObj.outerWidth() - targetObj.width()));
                } else {

                    targetObj.width(width);
                }

            }

        }, checkbox: {init: function (container, options) {

                var gridCheckBoxObj = $("<input type=\"checkbox\">").appendTo(container);
                gridCheckBoxObj.val(options.on);
                gridCheckBoxObj.attr("offval", options.off);
                return gridCheckBoxObj;
            }, getValue: function (target) {

                if ($(target).attr("checked")) {

                    return $(target).val();
                } else {

                    return $(target).attr("offval");
                }

            }, setValue: function (target, value) {

                if ($(target).val() == value) {

                    $(target).attr("checked", true);
                } else {

                    $(target).attr("checked", false);
                }

            }

        }, numberbox: {init: function (container, options) {

                var gridNumBoxObj = $("<input type=\"text\" class=\"datagrid-editable-input\">").appendTo(container);
                gridNumBoxObj.numberbox(options);
                return gridNumBoxObj;
            }, destroy: function (target) {

                $(target).numberbox("destroy");
            }, getValue: function (target) {

                return $(target).val();
            }, setValue: function (target, value) {

                $(target).val(value);
            }, resize: function (target, width) {

                var targetObj = $(target);
                if ($.boxModel == true) {

                    targetObj.width(width - (targetObj.outerWidth() - targetObj.width()));
                } else {

                    targetObj.width(width);
                }

            }

        }, validatebox: {init: function (container, options) {

                var gridvalidateBoxObj = $("<input type=\"text\" class=\"datagrid-editable-input\">").appendTo(container);
                gridvalidateBoxObj.validatebox(options);
                return gridvalidateBoxObj;
            }, destroy: function (target) {

                $(target).validatebox("destroy");
            }, getValue: function (target) {

                return $(target).val();
            }, setValue: function (target, value) {

                $(target).val(value);
            }, resize: function (taget, width) {

                var targetObj = $(taget);
                if ($.boxModel == true) {

                    targetObj.width(width - (targetObj.outerWidth() - targetObj.width()));
                } else {

                    targetObj.width(width);
                }

            }

        }, datebox: {init: function (container, options) {

                var gridDateBoxObj = $("<input type=\"text\">").appendTo(container);
                gridDateBoxObj.datebox(options);
                return gridDateBoxObj;
            }, destroy: function (target) {

                $(target).datebox("destroy");
            }, getValue: function (target) {

                return $(target).datebox("getValue");
            }, setValue: function (target, value) {

                $(target).datebox("setValue", value);
            }, resize: function (target, width) {

                $(target).datebox("resize", width);
            }

        }, combobox: {init: function (container, options) {

                var gridcomboxObj = $("<input type=\"text\">").appendTo(container);
                gridcomboxObj.combobox(options || {});
                return gridcomboxObj;
            }, destroy: function (target) {

                $(target).combobox("destroy");
            }, getValue: function (target) {

                return $(target).combobox("getValue");
            }, setValue: function (target, value) {

                $(target).combobox("setValue", value);
            }, resize: function (target, width) {

                $(target).combobox("resize", width);
            }

        }, combotree: {init: function (container, options) {

                var gridcombotreeObj = $("<input type=\"text\">").appendTo(container);
                gridcombotreeObj.combotree(options);
                return gridcombotreeObj;
            }, destroy: function (target) {

                $(target).combotree("destroy");
            }, getValue: function (target) {

                return $(target).combotree("getValue");
            }, setValue: function (target, value) {

                $(target).combotree("setValue", value);
            }, resize: function (target, width) {

                $(target).combotree("resize", width);
            }

        }

    };
})(jQuery);