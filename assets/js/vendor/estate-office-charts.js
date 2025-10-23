(function(window){
    'use strict';

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function toNumber(value) {
        var parsed = parseFloat(value);
        return isFinite(parsed) ? parsed : 0;
    }

    function pickColor(source, index, fallback) {
        if (Array.isArray(source) && source.length) {
            return source[index % source.length] || fallback;
        }
        if (typeof source === 'string' && source) {
            return source;
        }
        return fallback;
    }

    function Chart(ctx, config) {
        if (!(this instanceof Chart)) {
            return new Chart(ctx, config);
        }
        this.ctx = ctx;
        this.canvas = ctx.canvas;
        this.config = config || {};
        this.size = { width: this.canvas.width, height: this.canvas.height };
        this.render();
    }

    Chart.prototype.resize = function resize() {
        var ratio = window.devicePixelRatio || 1;
        var width = this.canvas.clientWidth || this.canvas.width || 420;
        var height = this.canvas.clientHeight || this.canvas.height || 260;
        width = Math.max(width, 240);
        height = Math.max(height, 200);

        if (this.canvas.width !== width * ratio || this.canvas.height !== height * ratio) {
            this.canvas.width = width * ratio;
            this.canvas.height = height * ratio;
        }

        this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        this.size = { width: width, height: height };
    };

    Chart.prototype.clear = function clear() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    };

    Chart.prototype.render = function render() {
        this.resize();
        this.clear();

        var type = (this.config.type || 'bar').toLowerCase();
        var data = this.config.data || {};
        var datasets = data.datasets || [];
        if (!datasets.length) {
            return;
        }

        var dataset = datasets[0];
        if (type === 'doughnut') {
            this.drawDoughnut(data, dataset);
        } else if (type === 'line') {
            this.drawLine(data, dataset);
        } else {
            this.drawBar(data, dataset);
        }
    };

    Chart.prototype.drawAxes = function drawAxes(padding) {
        var ctx = this.ctx;
        var width = this.size.width;
        var height = this.size.height;
        ctx.save();
        ctx.strokeStyle = '#d1d5db';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(padding, padding);
        ctx.lineTo(padding, height - padding);
        ctx.lineTo(width - padding, height - padding);
        ctx.stroke();
        ctx.restore();
    };

    Chart.prototype.drawLabels = function drawLabels(labels, positions, baselineY) {
        var ctx = this.ctx;
        ctx.save();
        ctx.fillStyle = '#4b5563';
        ctx.font = '12px sans-serif';
        ctx.textAlign = 'center';
        for (var i = 0; i < positions.length; i += 1) {
            var point = positions[i];
            var label = labels[i] != null ? String(labels[i]) : '';
            ctx.fillText(label, point.x, baselineY);
        }
        ctx.restore();
    };

    Chart.prototype.drawLine = function drawLine(data, dataset) {
        var ctx = this.ctx;
        var labels = data.labels || [];
        var values = (dataset.data || []).map(toNumber);
        var width = this.size.width;
        var height = this.size.height;
        var padding = 42;
        var availableWidth = width - padding * 2;
        var availableHeight = height - padding * 2;
        var maxValue = Math.max.apply(null, values.concat([0]));
        var topValue = maxValue > 0 ? maxValue : 1;

        this.drawAxes(padding);

        var points = [];
        var step = values.length > 1 ? availableWidth / (values.length - 1) : 0;
        for (var i = 0; i < values.length; i += 1) {
            var x;
            if (values.length === 1) {
                x = padding + availableWidth / 2;
            } else {
                x = padding + step * i;
            }
            var percent = topValue ? clamp(values[i] / topValue, 0, 1) : 0;
            var y = height - padding - percent * availableHeight;
            points.push({ x: x, y: y, value: values[i] });
        }

        ctx.save();
        if (dataset.fill) {
            ctx.beginPath();
            for (var j = 0; j < points.length; j += 1) {
                var point = points[j];
                if (j === 0) {
                    ctx.moveTo(point.x, point.y);
                } else {
                    ctx.lineTo(point.x, point.y);
                }
            }
            if (points.length) {
                ctx.lineTo(points[points.length - 1].x, height - padding);
                ctx.lineTo(points[0].x, height - padding);
            }
            ctx.closePath();
            ctx.fillStyle = dataset.backgroundColor || 'rgba(37,99,235,0.25)';
            ctx.fill();
        }

        ctx.beginPath();
        for (var k = 0; k < points.length; k += 1) {
            var pointLine = points[k];
            if (k === 0) {
                ctx.moveTo(pointLine.x, pointLine.y);
            } else {
                ctx.lineTo(pointLine.x, pointLine.y);
            }
        }
        ctx.strokeStyle = dataset.borderColor || '#2563eb';
        ctx.lineWidth = dataset.borderWidth || 2;
        ctx.stroke();

        ctx.fillStyle = dataset.borderColor || '#2563eb';
        for (var m = 0; m < points.length; m += 1) {
            var pointCircle = points[m];
            ctx.beginPath();
            ctx.arc(pointCircle.x, pointCircle.y, 3, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.restore();

        this.drawLabels(labels, points, height - padding + 16);

        ctx.save();
        ctx.fillStyle = '#4b5563';
        ctx.font = '12px sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(String(Math.round(topValue)), padding - 6, padding + 4);
        ctx.fillText('0', padding - 6, height - padding + 4);
        ctx.restore();
    };

    Chart.prototype.drawBar = function drawBar(data, dataset) {
        var ctx = this.ctx;
        var labels = data.labels || [];
        var values = (dataset.data || []).map(toNumber);
        var width = this.size.width;
        var height = this.size.height;
        var padding = 42;
        var availableWidth = width - padding * 2;
        var availableHeight = height - padding * 2;
        var maxValue = Math.max.apply(null, values.concat([0]));
        var topValue = maxValue > 0 ? maxValue : 1;
        var slot = values.length ? availableWidth / values.length : availableWidth;
        var barWidth = slot * 0.6;

        this.drawAxes(padding);

        ctx.save();
        for (var i = 0; i < values.length; i += 1) {
            var percent = topValue ? clamp(values[i] / topValue, 0, 1) : 0;
            var barHeight = percent * availableHeight;
            var x = padding + slot * i + (slot - barWidth) / 2;
            var y = height - padding - barHeight;
            var color = pickColor(dataset.backgroundColor, i, '#0ea5e9');
            var border = pickColor(dataset.borderColor, i, color);
            ctx.fillStyle = color;
            ctx.strokeStyle = border;
            ctx.lineWidth = dataset.borderWidth || 1;
            if (typeof ctx.roundRect === 'function') {
                ctx.beginPath();
                ctx.roundRect(x, y, barWidth, barHeight, 6);
                ctx.fill();
                if (dataset.borderColor) {
                    ctx.stroke();
                }
            } else {
                ctx.fillRect(x, y, barWidth, barHeight);
                if (dataset.borderColor) {
                    ctx.strokeRect(x, y, barWidth, barHeight);
                }
            }
        }
        ctx.restore();

        var points = [];
        for (var j = 0; j < values.length; j += 1) {
            var pointX = padding + slot * j + slot / 2;
            points.push({ x: pointX });
        }
        this.drawLabels(labels, points, height - padding + 16);

        ctx.save();
        ctx.fillStyle = '#4b5563';
        ctx.font = '12px sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(String(Math.round(topValue)), padding - 6, padding + 4);
        ctx.fillText('0', padding - 6, height - padding + 4);
        ctx.restore();
    };

    Chart.prototype.drawDoughnut = function drawDoughnut(data, dataset) {
        var ctx = this.ctx;
        var values = (dataset.data || []).map(function(value){
            return Math.max(0, toNumber(value));
        });
        var width = this.size.width;
        var height = this.size.height;
        var radius = Math.min(width, height) / 2 - 24;
        var centerX = width / 2;
        var centerY = height / 2;
        var total = values.reduce(function(acc, value){ return acc + value; }, 0);
        var startAngle = -Math.PI / 2;

        ctx.save();
        if (total <= 0) {
            ctx.fillStyle = '#e5e7eb';
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
            ctx.fill();
        } else {
            for (var i = 0; i < values.length; i += 1) {
                var portion = total ? values[i] / total : 0;
                var endAngle = startAngle + portion * Math.PI * 2;
                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.fillStyle = pickColor(dataset.backgroundColor, i, '#2563eb');
                ctx.arc(centerX, centerY, radius, startAngle, endAngle);
                ctx.closePath();
                ctx.fill();
                startAngle = endAngle;
            }
        }
        ctx.restore();

        ctx.save();
        ctx.beginPath();
        ctx.fillStyle = '#ffffff';
        ctx.arc(centerX, centerY, radius * 0.55, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        ctx.save();
        ctx.fillStyle = '#0f172a';
        ctx.font = 'bold 22px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(String(Math.round(total)), centerX, centerY - 4);
        ctx.font = '12px sans-serif';
        ctx.fillStyle = '#6b7280';
        var totalLabel = (window.EstateOfficeChartLabels && window.EstateOfficeChartLabels.total) || 'Łącznie';
        ctx.fillText(totalLabel, centerX, centerY + 16);
        ctx.restore();
    };

    window.Chart = Chart;
})(window);
