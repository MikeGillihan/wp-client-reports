(function( $ ) {
    "use strict";

	$( document ).ready(function() {

		$("#wp-client-reports-date-chooser-button").click(function() {
            $("#wp-client-reports-date-chooser").toggle();
        });

        $("#wp-client-reports-cancel").click(function() {
            $("#wp-client-reports-date-chooser").hide();
        });

        $("#wp-client-reports-apply").click(function() {
            $("#wp-client-reports-date-chooser").hide();
            var startDate = $("#from_value").val();
            var endDate = $("#to_value").val();
            if (!startDate && endDate) {
                startDate = endDate;
            } else if (!endDate && startDate) {
                endDate = startDate;
            }
            getData(startDate, endDate);
            setDates(startDate, endDate, null);
        });

        $("#wp-client-reports-force-refresh").click(function() {
            var dataString = 'action=wp_client_reports_force_refresh';
            $.ajax({
                type: "GET",
                url: ajaxurl,
                data: dataString,
                dataType: 'json',
                success: function(data, err) {
                    location.reload();
                }
            });
        });

        $("#wp-client-reports-quick-today").click(function(e) {
            e.preventDefault();
            var label = $(this).text();
            $("#wp-client-reports-date-chooser").hide();
            var siteUTCOffset = getSiteUTCOffset();
            var startDate = moment().utcOffset(siteUTCOffset).format("YYYY-MM-DD");
            var endDate = moment().utcOffset(siteUTCOffset).format("YYYY-MM-DD");
            setDates(startDate, endDate, label);
            getData(startDate, endDate);
        });

        $("#wp-client-reports-quick-yesterday").click(function(e) {
            e.preventDefault();
            var label = $(this).text();
            $("#wp-client-reports-date-chooser").hide();
            var siteUTCOffset = getSiteUTCOffset();
            var startDate = moment().utcOffset(siteUTCOffset).subtract(1, 'days').format("YYYY-MM-DD");
            var endDate = moment().utcOffset(siteUTCOffset).subtract(1, 'days').format("YYYY-MM-DD");
            setDates(startDate, endDate, label);
            getData(startDate, endDate);
        });

        $("#wp-client-reports-quick-last7").click(function(e) {
            e.preventDefault();
            var label = $(this).text();
            $("#wp-client-reports-date-chooser").hide();
            var siteUTCOffset = getSiteUTCOffset();
            var startDate = moment().utcOffset(siteUTCOffset).subtract(7, 'days').format("YYYY-MM-DD");
            var endDate = moment().utcOffset(siteUTCOffset).format("YYYY-MM-DD");
            setDates(startDate, endDate, label);
            getData(startDate, endDate);
        });

        $("#wp-client-reports-quick-last14").click(function(e) {
            e.preventDefault();
            var label = $(this).text();
            $("#wp-client-reports-date-chooser").hide();
            var siteUTCOffset = getSiteUTCOffset();
            var startDate = moment().utcOffset(siteUTCOffset).subtract(14, 'days').format("YYYY-MM-DD");
            var endDate = moment().utcOffset(siteUTCOffset).format("YYYY-MM-DD");
            setDates(startDate, endDate, label);
            getData(startDate, endDate);
        });

        $("#wp-client-reports-quick-last30").click(function(e) {
            e.preventDefault();
            var label = $(this).text();
            $("#wp-client-reports-date-chooser").hide();
            var siteUTCOffset = getSiteUTCOffset();
            var startDate = moment().utcOffset(siteUTCOffset).subtract(30, 'days').format("YYYY-MM-DD");
            var endDate = moment().utcOffset(siteUTCOffset).format("YYYY-MM-DD");
            setDates(startDate, endDate, label);
            getData(startDate, endDate);
        });

        $("#wp-client-reports-quick-last90").click(function(e) {
            e.preventDefault();
            var label = $(this).text();
            $("#wp-client-reports-date-chooser").hide();
            var siteUTCOffset = getSiteUTCOffset();
            var startDate = moment().utcOffset(siteUTCOffset).subtract(90, 'days').format("YYYY-MM-DD");
            var endDate = moment().utcOffset(siteUTCOffset).format("YYYY-MM-DD");
            setDates(startDate, endDate, label);
            getData(startDate, endDate);
        });

        $("#wp-client-reports-quick-lastmonth").click(function(e) {
            e.preventDefault();
            var label = $(this).text();
            $("#wp-client-reports-date-chooser").hide();
            var siteUTCOffset = getSiteUTCOffset();
            var startDate = moment().utcOffset(siteUTCOffset).subtract(1, 'month').startOf('month').format("YYYY-MM-DD");
            var endDate = moment().utcOffset(siteUTCOffset).subtract(1, 'month').endOf("month").format("YYYY-MM-DD");
            setDates(startDate, endDate, label);
            getData(startDate, endDate);
        });

        $("#wp-client-reports-quick-thismonth").click(function(e) {
            e.preventDefault();
            var label = $(this).text();
            $("#wp-client-reports-date-chooser").hide();
            var siteUTCOffset = getSiteUTCOffset();
            var startDate = moment().utcOffset(siteUTCOffset).startOf('month').format("YYYY-MM-DD");
            var endDate = moment().utcOffset(siteUTCOffset).endOf("month").format("YYYY-MM-DD");
            setDates(startDate, endDate, label);
            getData(startDate, endDate);
        });

        var siteUTCOffset = getSiteUTCOffset();
        getData(moment().utcOffset(siteUTCOffset).subtract(30, 'days').format("YYYY-MM-DD"), moment().utcOffset(siteUTCOffset).format("YYYY-MM-DD"));
        setDates(moment().utcOffset(siteUTCOffset).subtract(30, 'days').format("YYYY-MM-DD"), moment().utcOffset(siteUTCOffset).format("YYYY-MM-DD"), "Last 30 Days");

        $("#date-range").datepicker({
            maxDate: 0,
            firstDay: 0,
            numberOfMonths: [2,1],
            dateFormat: 'yy-mm-dd',
            beforeShowDay: function(date) {
                var instance = $( this ).data( "datepicker" );
                var date1 = $.datepicker.parseDate(instance.settings.dateFormat, $("#from_value").val());
                var date2 = $.datepicker.parseDate(instance.settings.dateFormat, $("#to_value").val());
                var isHightlight = date1 && ((date.getTime() == date1.getTime()) || (date2 && date >= date1 && date <= date2));
                return [true, isHightlight ? "dp-highlight" : ""];
            },
            onSelect: function(dateText, inst) {
                var js_date_format = getDateFormat();
                var instance = inst;
                var date1 = $.datepicker.parseDate(instance.settings.dateFormat, $("#from_value").val());
                var date2 = $.datepicker.parseDate(instance.settings.dateFormat, $("#to_value").val());
                var selectedDate = $.datepicker.parseDate(instance.settings.dateFormat, dateText);
                if (!date1 || date2) {
                    $(".from_value").val(dateText);
                    $("#wp-client-reports-start-date").text(moment(dateText).format(js_date_format));
                    $(".to_value").val("");
                } else if (selectedDate < date1) {
                    $(".to_value").val($("#from_value").val());
                    $(".from_value").val(dateText);
                    $("#wp-client-reports-end-date").text(moment(dateText).format(js_date_format));
                } else {
                    $(".to_value").val(dateText);
                    $("#wp-client-reports-end-date").text(moment(dateText).format(js_date_format));
                }
                //$(this).datepicker(); //not really sure why this was here?
            }
        });

        var originalReportTitle = $("#wp-client-reports-send-email-report #report-title").val();
        var originalReportIntro = $("#wp-client-reports-send-email-report #report-intro").val();

        $("#wp-client-reports-email-report").click(function(e) {
            var js_date_format = getDateFormat();
            var month = moment($(".to_value").val()).format('MMMM');
            var year = moment($(".to_value").val()).format('YYYY');
            var date = moment($(".to_value").val()).format(js_date_format);
            var newReportTitle = originalReportTitle;
            var newReportIntro = originalReportIntro;
            newReportTitle = newReportTitle.replace("[YEAR]", year);
            newReportTitle = newReportTitle.replace("[MONTH]", month);
            newReportTitle = newReportTitle.replace("[DATE]", date);
            newReportIntro = newReportIntro.replace("[YEAR]", year);
            newReportIntro = newReportIntro.replace("[MONTH]", month);
            newReportIntro = newReportIntro.replace("[DATE]", date);
            $("#wp-client-reports-send-email-report #report-title").val(newReportTitle);
            $("#wp-client-reports-send-email-report #report-intro").val(newReportIntro);
            $("#wp-client-reports-report-status").hide();
            $("#wp-client-reports-send-email-report").show();
        });
        

        $("#wp-client-reports-send-email-report").submit(function(e) {
            e.preventDefault();
            $("#send-report-spinner").show();
            $('#wp-client-reports-send-email-report .button-primary').prop('disabled', true);
            var dataString = $("#wp-client-reports-send-email-report").serialize();
            $.ajax({
                type: "POST",
                url: ajaxurl,
                data: dataString,
                dataType: 'json',
                success: function(data, err) {
                    if (data.status == 'success') {
                        $("#wp-client-reports-send-email-report").hide();
                        $("#send-report-spinner").hide();
                        $("#wp-client-reports-report-status").addClass('wp-client-reports-success').removeClass('wp-client-reports-error').show().find('p').text(data.message);
                        $('#wp-client-reports-send-email-report .button-primary').prop('disabled', false);
                    } else {
                        $("#wp-client-reports-send-email-report").hide();
                        $("#send-report-spinner").hide();
                        $("#wp-client-reports-report-status").addClass('wp-client-reports-error').removeClass('wp-client-reports-success').show().find('p').text(data.message);
                        $('#wp-client-reports-send-email-report .button-primary').prop('disabled', false);
                    }
                }
            });
        });

    });

    function setDates(startDate, endDate, label) {
        var js_date_format = getDateFormat();
        var start_date_formatted = moment(startDate).format(js_date_format);
        var end_date_formatted = moment(endDate).format(js_date_format);
        $(".from_value").val(startDate);
        $(".to_value").val(endDate);
        $("#wp-client-reports-start-date").text(start_date_formatted);
        $("#wp-client-reports-end-date").text(end_date_formatted);
        if (label) {
            $("#wp-client-reports-button-label").text(label);
        } else {
            $("#wp-client-reports-button-label").text(start_date_formatted + " - " + end_date_formatted);
        }
        $("#date-range").datepicker( "refresh" );
    }

    function getData(startDate, endDate) {
        var start_date_clean = moment(startDate).format("YYYY-MM-DD"); //.utc()
        var end_date_clean = moment(endDate).format("YYYY-MM-DD"); //.utc()
        $(document).trigger('wp_client_reports_js_get_data', [start_date_clean, end_date_clean]);
    }

    $(document).on('wp_client_reports_js_get_data', function(event, start_date_utc, end_date_utc){
        if ($('#wp-client-reports-updates').length) {
            $('#wp-client-reports-updates').addClass('loading');
            var dataString = 'action=wp_client_reports_updates_data&start=' + start_date_utc + '&end=' + end_date_utc;
            var js_date_format = getDateFormat();
            $.ajax({
                type: "GET",
                url: ajaxurl,
                data: dataString,
                dataType: 'json',
                success: function(data, err) {
                    $("#wp-client-reports-total-update-count").text(data.total_updates);
                    $("#wp-client-reports-wp-update-count").text(data.wp_updated);
                    $("#wp-client-reports-plugin-update-count").text(data.total_plugins_updated);
                    $("#wp-client-reports-theme-update-count").text(data.total_themes_updated);
                    $("#wp-client-reports-wp-updates-list").html("");
                    $("#wp-client-reports-plugin-updates-list").html("");
                    $("#wp-client-reports-theme-updates-list").html("");
                    $.each(data.updates, function( index, update ) {
                        var date_formatted = moment(update.date).format(js_date_format);
                        var newUpdate = '<li><strong class="wp-client-reports-name">' + update.name + '</strong><span class="wp-client-reports-from-to">' + update.version_before + ' <span class="dashicons dashicons-arrow-right-alt"></span> ' + update.version_after + '</span><span class="wp-client-reports-date">' + date_formatted + '</span></li>';
                        if (update.type == 'wp') {
                            $("#wp-client-reports-wp-updates-list").append(newUpdate)
                        } else if (update.type == 'plugin') {
                            $("#wp-client-reports-plugin-updates-list").append(newUpdate)
                        } else if (update.type == 'theme') {
                            $("#wp-client-reports-theme-updates-list").append(newUpdate)
                        }
                    });
                    if (data.wp_updated === 0) {
                        $("#wp-client-reports-wp-updates-list").append('<li class="wp-client-reports-empty">' + wp_client_reports_data.nowpupdates + '</li>');
                    }
                    if (data.total_plugins_updated === 0) {
                        $("#wp-client-reports-plugin-updates-list").append('<li class="wp-client-reports-empty">' + wp_client_reports_data.nopluginupdates + '</li>');
                    }
                    if (data.total_themes_updated === 0) {
                        $("#wp-client-reports-theme-updates-list").append('<li class="wp-client-reports-empty">' + wp_client_reports_data.nothemeupdates + '</li>');
                    }
                    $('#wp-client-reports-updates').removeClass('loading');
                }
            });
        }
    });

    $(document).on('wp_client_reports_js_get_data', function(event, start_date_utc, end_date_utc){
        if ($('#wp-client-reports-content-stats').length) {
            $('#wp-client-reports-content-stats').addClass('loading');
            var dataString = 'action=wp_client_reports_content_stats_data&start=' + start_date_utc + '&end=' + end_date_utc;
            var js_date_format = getDateFormat();
            $.ajax({
                type: "GET",
                url: ajaxurl,
                data: dataString,
                dataType: 'json',
                success: function(data, err) {
                    $("#wp-client-reports-new-posts-count").text(data.posts_count);
                    $("#wp-client-reports-new-pages-count").text(data.pages_count);
                    $("#wp-client-reports-new-comments-count").text(data.comments_count);
                    $('#wp-client-reports-content-stats').removeClass('loading');
                }
            });
        }
    });

    function getDateFormat() {
        if (wp_client_reports_data.moment_date_format) {
            return wp_client_reports_data.moment_date_format;
        } else {
            return 'MM/DD/YYYY';
        }
    }

    function getSiteUTCOffset() {
        if (wp_client_reports_data.site_utc_offset) {
            return wp_client_reports_data.site_utc_offset * 60;
        } else {
            return moment().utcOffset();
        }
    }

}(jQuery));
