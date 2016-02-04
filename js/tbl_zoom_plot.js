function displayHelp(){var a=PMA_ajaxShowMessage(PMA_messages.strDisplayHelp,1E4);a.click(function(){PMA_ajaxRemoveMessage(a)})}Array.max=function(a){return Math.max.apply(Math,a)};Array.min=function(a){return Math.min.apply(Math,a)};function isNumeric(a){return!isNaN(parseFloat(a))&&isFinite(a)}function isEmpty(a){for(var c in a)return false;return true}
function getDate(a,c){if(c.toString().search(/datetime/i)!=-1||c.toString().search(/timestamp/i)!=-1)return Highcharts.dateFormat("%Y-%m-%e %H:%M:%S",a);else if(c.toString().search(/time/i)!=-1)return Highcharts.dateFormat("%H:%M:%S",a);else if(c.toString().search(/date/i)!=-1)return Highcharts.dateFormat("%Y-%m-%e",a)}
function getTimeStamp(a,c){if(c.toString().search(/datetime/i)!=-1||c.toString().search(/timestamp/i)!=-1)return getDateFromFormat(a,"yyyy-MM-dd HH:mm:ss",a);else if(c.toString().search(/time/i)!=-1)return getDateFromFormat("1970-01-01 "+a,"yyyy-MM-dd HH:mm:ss");else if(c.toString().search(/date/i)!=-1)return getDateFromFormat(a,"yyyy-MM-dd")}
function getType(a){return a.toString().search(/int/i)!=-1||a.toString().search(/decimal/i)!=-1||a.toString().search(/year/i)!=-1?"numeric":a.toString().search(/time/i)!=-1||a.toString().search(/date/i)!=-1?"time":"text"}function getCord(a){var c=[],h=$.extend(true,[],a);a=jQuery.unique(a).sort();$.each(h,function(j,l){c.push(jQuery.inArray(l,a))});return[c,a,h]}function scrollToChart(){var a=$("#dataDisplay").offset().top-100;$("html,body").animate({scrollTop:a},500)}
function includePan(a){var c,h,j,l=$("#resizer").width()-3,o=$("#resizer").height()-20;$("#querychart").mousedown(function(){c=1});$("#querychart").mouseup(function(){c=0});$("#querychart").mousemove(function(k){if(c==1){if(k.pageX>h){var e=a.xAxis[0].getExtremes(),g=(k.pageX-h)*(e.max-e.min)/l;a.xAxis[0].setExtremes(e.min-g,e.max-g)}else if(k.pageX<h){e=a.xAxis[0].getExtremes();g=(h-k.pageX)*(e.max-e.min)/l;a.xAxis[0].setExtremes(e.min+g,e.max+g)}if(k.pageY>j){e=a.yAxis[0].getExtremes();g=1*(k.pageY-
j)*(e.max-e.min)/o;a.yAxis[0].setExtremes(e.min+g,e.max+g)}else if(k.pageY<j){e=a.yAxis[0].getExtremes();g=1*(j-k.pageY)*(e.max-e.min)/o;a.yAxis[0].setExtremes(e.min-g,e.max-g)}}h=k.pageX;j=k.pageY})}
$(document).ready(function(){$("input[name='mode']:checked").val();var a=null,c=null,h=$("#tableid_0").val(),j=$("#tableid_1").val(),l=$("#types_0").val(),o=$("#types_1").val(),k=$("#dataLabel").val(),e=1,g=jQuery.parseJSON($("#querydata").html());$("#tableid_0").change(function(){$("#zoom_search_form").submit()});$("#tableid_1").change(function(){$("#zoom_search_form").submit()});$("#tableid_2").change(function(){$("#zoom_search_form").submit()});$("#tableid_3").change(function(){$("#zoom_search_form").submit()});
$("#inputFormSubmitId").click(function(){if($("#tableid_0").get(0).selectedIndex==0||$("#tableid_1").get(0).selectedIndex==0)PMA_ajaxShowMessage(PMA_messages.strInputNull);else h==j&&PMA_ajaxShowMessage(PMA_messages.strSameInputs)});$('<div id="togglesearchformdiv"><a id="togglesearchformlink"></a></div>').insertAfter("#zoom_search_form").hide();$("#togglesearchformlink").html(PMA_messages.strShowSearchCriteria).bind("click",function(){var i=$(this);$("#zoom_search_form").slideToggle();i.text()==
PMA_messages.strHideSearchCriteria?i.text(PMA_messages.strShowSearchCriteria):i.text(PMA_messages.strHideSearchCriteria);return false});var C={};C[PMA_messages.strSave]=function(){var i={},b={},d=4,t=false,I=false;for(key in u){var K=u[key],A=$("#fields_null_id_"+d).attr("checked")?null:$("#fieldID_"+d).val();if(A instanceof Array)A=$("#fieldID_"+d).map(function(){return $(this).val()}).get().join(",");if(K!=A){u[key]=A;i[key]=A;if(key==h){t=true;g[c][h]=A}else if(key==j){I=true;g[c][j]=A}}if($("#fieldID_"+
d).hasClass("bit"))b[key]="bit";d++}if(t||I){var v=[];v[0]={};v[0].marker={symbol:"circle"};if(t){m[c]=u[h];if(l=="numeric"){a.series[0].data[c].update({x:u[h]});a.xAxis[0].setExtremes(Array.min(m)-6,Array.max(m)+6)}else if(l=="time")a.series[0].data[c].update({x:getTimeStamp(u[h],$("#types_0").val())});else{var w=getCord(m),x=getCord(n),p=0;v[0].data=[];m=w[2];n=x[2];$.each(g,function(D,B){o!="text"?v[0].data.push({name:B[k],x:w[0][p],y:B[j],marker:{fillColor:y[p%8]},id:p}):v[0].data.push({name:B[k],
x:w[0][p],y:x[0][p],marker:{fillColor:y[p%8]},id:p});p++});f.xAxis.labels={formatter:function(){return w[1][this.value]&&w[1][this.value].length>10?w[1][this.value].substring(0,10):w[1][this.value]}};f.series=v;a=PMA_createChart(f)}}if(I){n[c]=u[j];if(o=="numeric"){a.series[0].data[c].update({y:u[j]});a.yAxis[0].setExtremes(Array.min(n)-6,Array.max(n)+6)}else if(o=="time")a.series[0].data[c].update({y:getTimeStamp(u[j],$("#types_1").val())});else{w=getCord(m);x=getCord(n);p=0;v[0].data=[];m=w[2];
n=x[2];$.each(g,function(D,B){l!="text"?v[0].data.push({name:B[k],x:B[h],y:x[0][p],marker:{fillColor:y[p%8]},id:p}):v[0].data.push({name:B[k],x:w[0][p],y:x[0][p],marker:{fillColor:y[p%8]},id:p});p++});f.yAxis.labels={formatter:function(){return x[1][this.value]&&x[1][this.value].length>10?x[1][this.value].substring(0,10):x[1][this.value]}};f.series=v;a=PMA_createChart(f)}}a.series[0].data[c].select()}if(!isEmpty(i)){d="UPDATE `"+window.parent.table+"` SET ";for(key in i){d+="`"+key+"`=";t=i[key];
if(t==null)d+="NULL, ";else if($.trim(t)=="")d+="'', ";else if(b[key]!=null){if(b[key]=="bit")d+="b'"+t+"', "}else d+=isNumeric(t)?t+", ":"'"+t+"', "}d=d.substring(0,d.length-2);d+=" WHERE "+PMA_urldecode(g[c].where_clause);$.post("sql.php",{token:window.parent.token,db:window.parent.db,ajax_request:true,sql_query:d,inline_edit:false},function(D){if(D.success==true){$("#sqlqueryresults").html(D.sql_query);$("#sqlqueryresults").trigger("appendAnchor")}else PMA_ajaxShowMessage(D.error,false)})}$("#dataDisplay").dialog("close")};
C[PMA_messages.strCancel]=function(){$(this).dialog("close")};$("#dataDisplay").dialog({autoOpen:false,title:PMA_messages.strDataPointContent,modal:true,buttons:C,width:$("#dataDisplay").width()+24,open:function(){$(this).find("input[type=checkbox]").css("margin","0.5em")}});$("#dataDisplay").find(":input").live("keydown",function(i){if(i.which===13){i.preventDefault();typeof C[PMA_messages.strSave]==="function"&&C[PMA_messages.strSave].call()}});if(g!=null){$("#zoom_search_form").slideToggle().hide();
$("#togglesearchformlink").text(PMA_messages.strShowSearchCriteria);$("#togglesearchformdiv").show();var u,y=["#FF0000","#00FFFF","#0000FF","#0000A0","#FF0080","#800080","#FFFF00","#00FF00","#FF00FF"],z=[],m=[],n=[],r,s,q=0,E,F,G,H,f={chart:{renderTo:"querychart",type:"scatter",width:$("#resizer").width()-3,height:$("#resizer").height()-20},credits:{enabled:false},exporting:{enabled:false},label:{text:$("#dataLabel").val()},plotOptions:{series:{allowPointSelect:true,cursor:"pointer",showInLegend:false,
dataLabels:{enabled:false},point:{events:{click:function(){var i=this.id,b=4;c=i;$.post("tbl_zoom_select.php",{ajax_request:true,get_data_row:true,db:window.parent.db,table:window.parent.table,where_clause:g[i].where_clause,token:window.parent.token},function(d){for(key in d.row_info){$field=$("#fieldID_"+b);$field_null=$("#fields_null_id_"+b);if(d.row_info[key]==null){$field_null.attr("checked",true);$field.val("")}else{$field_null.attr("checked",false);$field.attr("multiple")?$field.val(d.row_info[key].split(",")):
$field.val(d.row_info[key])}b++}u={};u=d.row_info});$("#dataDisplay").dialog("open")}}}}},tooltip:{formatter:function(){return this.point.name}},title:{text:PMA_messages.strQueryResults},xAxis:{title:{text:$("#tableid_0").val()},events:{setExtremes:function(){this.resetZoom.show()}}},yAxis:{min:null,title:{text:$("#tableid_1").val()},endOnTick:false,startOnTick:false,events:{setExtremes:function(){this.resetZoom.show()}}}};if(k=="")f.tooltip.enabled=false;$("#resizer").resizable({resize:function(){a.setSize(this.offsetWidth-
3,this.offsetHeight-20,false)}});l=getType(l);o=getType(o);f.xAxis.type=l=="time"?"datetime":"linear";f.yAxis.type=o=="time"?"datetime":"linear";z[0]={};z[0].data=[];z[0].marker={symbol:"circle"};if(l!="text"&&o!="text"){$.each(g,function(i,b){var d=l=="numeric"?b[h]:getTimeStamp(b[h],$("#types_0").val()),t=o=="numeric"?b[j]:getTimeStamp(b[j],$("#types_1").val());z[0].data.push({name:b[k],x:d,y:t,marker:{fillColor:y[q%8]},id:q});m.push(b[h]);n.push(b[j]);q++});if(l=="numeric"){f.xAxis.max=Array.max(m)+
6;f.xAxis.min=Array.min(m)-6}else f.xAxis.labels={formatter:function(){return getDate(this.value,$("#types_0").val())}};if(o=="numeric"){f.yAxis.max=Array.max(n)+6;f.yAxis.min=Array.min(n)-6}else f.yAxis.labels={formatter:function(){return getDate(this.value,$("#types_1").val())}}}else if(l=="text"&&o!="text"){$.each(g,function(i,b){m.push(b[h]);n.push(b[j])});r=getCord(m);$.each(g,function(i,b){var d=o=="numeric"?b[j]:getTimeStamp(b[j],$("#types_1").val());z[0].data.push({name:b[k],x:r[0][q],y:d,
marker:{fillColor:y[q%8]},id:q});q++});f.xAxis.labels={formatter:function(){return r[1][this.value]&&r[1][this.value].length>10?r[1][this.value].substring(0,10):r[1][this.value]}};if(o=="numeric"){f.yAxis.max=Array.max(n)+6;f.yAxis.min=Array.min(n)-6}else f.yAxis.labels={formatter:function(){return getDate(this.value,$("#types_1").val())}};m=r[2]}else if(l!="text"&&o=="text"){$.each(g,function(i,b){m.push(b[h]);n.push(b[j])});s=getCord(n);$.each(g,function(i,b){var d=l=="numeric"?b[h]:getTimeStamp(b[h],
$("#types_0").val());z[0].data.push({name:b[k],y:s[0][q],x:d,marker:{fillColor:y[q%8]},id:q});q++});if(l=="numeric"){f.xAxis.max=Array.max(m)+6;f.xAxis.min=Array.min(m)-6}else f.xAxis.labels={formatter:function(){return getDate(this.value,$("#types_0").val())}};f.yAxis.labels={formatter:function(){return s[1][this.value]&&s[1][this.value].length>10?s[1][this.value].substring(0,10):s[1][this.value]}};n=s[2]}else if(l=="text"&&o=="text"){$.each(g,function(i,b){m.push(b[h]);n.push(b[j])});r=getCord(m);
s=getCord(n);$.each(g,function(i,b){z[0].data.push({name:b[k],x:r[0][q],y:s[0][q],marker:{fillColor:y[q%8]},id:q});q++});f.xAxis.labels={formatter:function(){return r[1][this.value]&&r[1][this.value].length>10?r[1][this.value].substring(0,10):r[1][this.value]}};f.yAxis.labels={formatter:function(){return s[1][this.value]&&s[1][this.value].length>10?s[1][this.value].substring(0,10):s[1][this.value]}};m=r[2];n=s[2]}f.series=z;a=PMA_createChart(f);F=a.xAxis[0].getExtremes().min;E=a.xAxis[0].getExtremes().max;
H=a.yAxis[0].getExtremes().min;G=a.yAxis[0].getExtremes().max;includePan(a);var J=function(){var i=H+(G-H)*(1-e)/2,b=G-(G-H)*(1-e)/2;a.xAxis[0].setExtremes(F+(E-F)*(1-e)/2,E-(E-F)*(1-e)/2);a.yAxis[0].setExtremes(i,b)};$("#querychart").mousewheel(function(i,b){if(b>0){if(e>0.1){e-=0.1;J()}}else if(b<0){e+=0.1;J()}});a.yAxis[0].resetZoom=a.xAxis[0].resetZoom=$('<a href="#">Reset zoom</a>').appendTo(a.container).css({position:"absolute",top:10,right:20,display:"none"}).click(function(){a.xAxis[0].setExtremes(null,
null);a.yAxis[0].setExtremes(null,null);this.style.display="none"});scrollToChart()}});
