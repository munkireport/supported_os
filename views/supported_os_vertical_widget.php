<div class="col-md-6">
    <div class="panel panel-default" id="os-supported-vertical-widget">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-level-up"></i>
                <span data-i18n="supported_os.highest_supported"></span>
                <list-link data-url="/show/listing/supported_os/supported_os"></list-link>
            </h3>
        </div>
        <div class="panel-body" style="overflow-x:auto; padding:0;">
            <svg id="os-bar-plot" style="height:300px;"></svg>
        </div>
    </div><!-- /panel -->
</div><!-- /col -->

<script>
$(document).on('appReady', function() {

    var osUrl = appUrl + '/module/machine/os';
    var supportedUrl = appUrl + '/module/supported_os/get_admin_data';

    const macosToDarwin = {
        14: 23,
        15: 24,
        26: 25,
        27: 26,
        28: 27,
        29: 28,
        30: 29,
        31: 30,
        32: 31,
        33: 32,
        34: 33,
        35: 34
    };

    d3.json(supportedUrl, function(err, supportedData) {
        if (err || !supportedData) {
            console.error('Error loading supported OS data:', err);
            return;
        }

        var supportedMacOSVersion = null;
        if (supportedData.current_os) {
            var currentOsStr = supportedData.current_os.toString();
            var match = currentOsStr.match(/^(\d{1,2})/);
            supportedMacOSVersion = match ? parseInt(match[1]) : null;
        }

        if (!supportedMacOSVersion) {
            console.warn('Could not determine supported macOS version from supported_os module');
            return;
        }

        var currentDarwinBuild = macosToDarwin[supportedMacOSVersion] !== undefined
            ? macosToDarwin[supportedMacOSVersion]
            : supportedMacOSVersion - 1;

        d3.json(osUrl, function(err, data) {
            if (err) {
                console.error('Error loading OS data:', err);
                return;
            }

            if (!data || !data.length) {
                console.warn('No OS data returned from endpoint.');
                return;
            }

            var grouped = {};
            data.forEach(function(d) {
                var versionStr = mr.integerToVersion(d.label);
                var majorMatch = versionStr.match(/^(\d+)/);
                var major = majorMatch ? parseInt(majorMatch[1]) : null;
                if (!major) return;

                if (!grouped[major]) {
                    grouped[major] = 0;
                }
                grouped[major] += +d.count;
            });

            if (!grouped[supportedMacOSVersion]) {
                grouped[supportedMacOSVersion] = 0;
            }

            var values = Object.keys(grouped).map(function(key) {
                var major = parseInt(key);
                var darwinBuild = macosToDarwin[major] !== undefined ? macosToDarwin[major] : major - 1;
                var count = grouped[key];

                // Assign negative y value for older Darwin builds (below n-2)
                var yCount = (darwinBuild < currentDarwinBuild - 2) ? -count : count;

                return {
                    key: "macOS " + major,
                    major: major,
                    darwinBuild: darwinBuild,
                    y: yCount
                };
            });

            // Sort descending by darwinBuild
            values.sort(function(a, b) {
                return b.darwinBuild - a.darwinBuild;
            });

            values.forEach(function(d) {
                if (d.darwinBuild === currentDarwinBuild) {
                    d.color = '#2ca02c'; // green current
                    d.status = 'supported';
                } else if (d.darwinBuild === currentDarwinBuild - 1 || d.darwinBuild === currentDarwinBuild - 2) {
                    d.color = '#ff7f0e'; // orange recent
                    d.status = 'recent';
                } else if (d.darwinBuild < currentDarwinBuild - 2) {
                    d.color = '#d62728'; // red old
                    d.status = 'old';
                } else {
                    d.color = '#999999'; // future/unknown
                    d.status = 'future';
                }
            });

            var chartData = [{
                key: "macOS Major Versions",
                values: values
            }];

            var minWidth = values.length * 60 + 175;
            var panelWidth = d3.select('#os-supported-vertical-widget .panel-body').style("width");
            var width = Math.max(minWidth, parseInt(panelWidth));

            nv.addGraph(function() {
                var chart = nv.models.discreteBarChart()
                    .x(function(d) { return d.key; })
                    .y(function(d) { return d.y; })
                    .staggerLabels(true)
                    .showValues(true)
                    .valueFormat(d3.format(','))
                    .duration(250)
                    .color(function(d) { return d.color; });

                chart.tooltip.enabled(true);
                chart.tooltip.contentGenerator(function(d) {
                    var val = Math.abs(d.data.y);  // Show count always positive in tooltip
                    var label = '';
                    if (d.data.status === 'supported') label = 'Current Supported Version';
                    else if (d.data.status === 'recent') label = 'Recent Version';
                    else if (d.data.status === 'old') label = 'Outdated Version';
                    else label = 'Future/Unknown Version';
                    return '<h3>' + d.data.key + '</h3><p>' + val + ' clients<br><em>' + label + '</em></p>';
                });

                d3.select('#os-bar-plot')
                    .datum(chartData)
                    .style("width", width + "px")
                    .call(chart);

                nv.utils.windowResize(function() {
                    panelWidth = d3.select('#os-supported-vertical-widget .panel-body').style("width");
                    width = Math.max(minWidth, parseInt(panelWidth));
                    d3.select('#os-bar-plot')
                        .style("width", width + "px")
                        .call(chart);
                });

                chart.discretebar.dispatch.on('elementClick', function(e) {
                    var majorVersion = e.data.major;
                    window.location.href = appUrl + '/show/listing/supported_os/supported_os#' + majorVersion;
                });

                return chart;
            });
        });
    });
});
</script>
