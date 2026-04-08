import * as d3 from 'd3';

const NODE_WIDTH = 300;
const NODE_HEIGHT = 112;
const HORIZONTAL_GAP = 190;
const VERTICAL_GAP = 40;
const DURATION = 250;
const NODE_TEXT_WIDTH = NODE_WIDTH - 52;
const LABEL_FONT_SIZE = 14;
const DESCRIPTION_FONT_SIZE = 12;
const LABEL_LINE_HEIGHT = 18;
const DESCRIPTION_LINE_HEIGHT = 16;

const DEFAULT_NODE_STYLE = {
    fill: '#2f2d2a',
    stroke: '#5b5954',
    text: '#d9d3cb',
};

function cloneTree(tree) {
    return JSON.parse(JSON.stringify(tree));
}

function collapseNode(node) {
    if (!node.children?.length) {
        return;
    }

    node._children = node.children;
    node._children.forEach(collapseNode);
    node.children = null;
}

document.addEventListener('alpine:init', () => {
    Alpine.data('org_chart_parse', ({ trees, errors, initial }) => ({
        activeTree: initial,
        errors,
        trees,
        svg: null,
        rootGroup: null,
        zoomBehavior: null,
        root: null,
        treeLayout: d3.tree().nodeSize([NODE_HEIGHT + VERTICAL_GAP, NODE_WIDTH + HORIZONTAL_GAP]),
        nodeId: 0,
        exporting: false,

        init() {
            this.renderTree(this.activeTree);
        },

        renderTree(name) {
            const tree = this.trees[name];

            this.activeTree = name;
            this.$refs.diagram.innerHTML = '';

            if (!tree || this.errors[name]) {
                return;
            }

            this.nodeId = 0;

            const width = this.$refs.diagram.clientWidth || 1200;
            const height = this.$refs.diagram.clientHeight || 700;

            this.svg = d3
                .select(this.$refs.diagram)
                .append('svg')
                .attr('class', 'h-full w-full')
                .attr('viewBox', [0, 0, width, height])
                .attr('preserveAspectRatio', 'xMidYMid meet')
                .style('font-family', 'Inter, sans-serif')
                .style('background', 'transparent');

            this.rootGroup = this.svg.append('g');

            this.zoomBehavior = d3
                .zoom()
                .scaleExtent([0.35, 2.5])
                .on('start', () => {
                    this.$refs.diagram.classList.add('is-dragging');
                })
                .on('end', () => {
                    this.$refs.diagram.classList.remove('is-dragging');
                })
                .on('zoom', (event) => {
                    this.rootGroup.attr('transform', event.transform);
                });

            this.svg.call(this.zoomBehavior);

            this.root = d3.hierarchy(cloneTree(tree));
            this.root.x0 = height / 2;
            this.root.y0 = 0;

            this.root.descendants().forEach((node) => {
                node.id = ++this.nodeId;
                node._children = node.children;
            });

            this.root.children?.forEach(collapseNode);

            this.update(this.root);
        },

        update(source) {
            this.treeLayout(this.root);

            const nodes = this.root.descendants();
            const links = this.root.links();
            const bounds = this.getBounds(nodes);

            this.rootGroup
                .selectAll('path.org-chart-link')
                .data(links, (d) => d.target.id)
                .join(
                    (enter) => enter
                        .append('path')
                        .attr('class', 'org-chart-link')
                        .attr('fill', 'none')
                        .attr('stroke', '#5a5956')
                        .attr('stroke-linecap', 'round')
                        .attr('stroke-width', 1.6)
                        .attr('opacity', 0.9)
                        .attr('d', () => {
                            const origin = {
                                x: source.x0 ?? source.x,
                                y: source.y0 ?? source.y,
                            };

                            return this.linkPath({ source: origin, target: origin });
                        })
                        .call((enter) => enter.transition().duration(DURATION).attr('d', this.linkPath)),
                    (update) => update.call((update) => update.transition().duration(DURATION).attr('d', this.linkPath)),
                    (exit) => exit
                        .transition()
                        .duration(DURATION)
                        .attr('d', () => {
                            const origin = {
                                x: source.x,
                                y: source.y,
                            };

                            return this.linkPath({ source: origin, target: origin });
                        })
                        .remove(),
                );

            const node = this.rootGroup
                .selectAll('g.org-chart-node')
                .data(nodes, (d) => d.id)
                .join(
                    (enter) => {
                        const group = enter
                            .append('g')
                            .attr('class', 'org-chart-node')
                            .attr('transform', () => `translate(${source.y0 ?? source.y},${source.x0 ?? source.x})`)
                            .style('cursor', (d) => ((d.children || d._children) ? 'pointer' : 'default'))
                            .on('click', (_, d) => {
                                if (!d.children && !d._children) {
                                    return;
                                }

                                d.children = d.children ? null : d._children;
                                this.update(d);
                            });

                        group
                            .append('rect')
                            .attr('x', -NODE_WIDTH / 2)
                            .attr('y', -NODE_HEIGHT / 2)
                            .attr('width', NODE_WIDTH)
                            .attr('height', NODE_HEIGHT)
                            .attr('rx', 10)
                            .attr('fill', (d) => this.nodeStyle(d).fill)
                            .attr('stroke', (d) => this.nodeStyle(d).stroke)
                            .attr('stroke-width', (d) => (d.depth === 0 || d.data.fill ? 2 : 1.5));

                        group
                            .append('text')
                            .attr('class', 'org-chart-label')
                            .attr('text-anchor', 'middle')
                            .attr('fill', (d) => this.nodeStyle(d).text)
                            .style('font-weight', (d) => (d.depth <= 1 ? 600 : 500))
                            .call((text) => this.renderNodeText(text));

                        group
                            .append('text')
                            .attr('class', 'org-chart-toggle')
                            .attr('x', NODE_WIDTH / 2 - 18)
                            .attr('y', 0)
                            .attr('text-anchor', 'middle')
                            .attr('dominant-baseline', 'middle')
                            .attr('fill', '#9f9c97')
                            .style('font-size', '16px')
                            .style('font-weight', 700)
                            .text((d) => this.toggleLabel(d));

                        return group;
                    },
                    (update) => update,
                    (exit) => exit
                        .transition()
                        .duration(DURATION)
                        .attr('transform', () => `translate(${source.y},${source.x})`)
                        .remove(),
                );

            node
                .select('rect')
                .transition()
                .duration(DURATION)
                .attr('fill', (d) => this.nodeStyle(d).fill)
                .attr('stroke', (d) => this.nodeStyle(d).stroke)
                .attr('stroke-width', (d) => (d.depth === 0 || d.data.fill ? 2 : 1.5));

            node
                .select('text.org-chart-label')
                .attr('fill', (d) => this.nodeStyle(d).text)
                .style('font-weight', (d) => (d.depth <= 1 ? 600 : 500))
                .call((text) => this.renderNodeText(text));

            node
                .select('text.org-chart-toggle')
                .text((d) => this.toggleLabel(d))
                .attr('display', (d) => ((d.children || d._children) ? 'block' : 'none'));

            node
                .transition()
                .duration(DURATION)
                .attr('transform', (d) => `translate(${d.y},${d.x})`);

            this.root.descendants().forEach((d) => {
                d.x0 = d.x;
                d.y0 = d.y;
            });

            this.fitToView(bounds);
        },

        linkPath(link) {
            return d3
                .linkHorizontal()
                .x((point) => point.y)
                .y((point) => point.x)({
                    source: {
                        x: link.source.x,
                        y: link.source.y + NODE_WIDTH / 2,
                    },
                    target: {
                        x: link.target.x,
                        y: link.target.y - NODE_WIDTH / 2,
                    },
                });
        },

        nodeStyle(node) {
            return {
                fill: node.data.fill || DEFAULT_NODE_STYLE.fill,
                stroke: node.data.stroke || DEFAULT_NODE_STYLE.stroke,
                text: node.data.text || DEFAULT_NODE_STYLE.text,
            };
        },

        displayLabel(node) {
            return node.data.number ? `${node.data.number} ${node.data.label}` : node.data.label;
        },

        renderNodeText(selection) {
            selection.each((d, index, nodes) => {
                const text = d3.select(nodes[index]);
                const label = this.displayLabel(d);
                const description = d.data.description?.trim() || null;
                const labelLines = this.wrapSvgText(text, label, NODE_TEXT_WIDTH, LABEL_FONT_SIZE, description ? 2 : 3);
                const descriptionLines = description
                    ? this.wrapSvgText(text, description, NODE_TEXT_WIDTH, DESCRIPTION_FONT_SIZE, 3)
                    : [];
                const contentHeight =
                    labelLines.length * LABEL_LINE_HEIGHT +
                    (descriptionLines.length ? 8 + descriptionLines.length * DESCRIPTION_LINE_HEIGHT : 0);
                let y = -contentHeight / 2 + LABEL_FONT_SIZE;

                text.selectAll('tspan').remove();
                text.attr('dominant-baseline', null);
                text.attr('y', null);

                labelLines.forEach((line, lineIndex) => {
                    text
                        .append('tspan')
                        .attr('x', 0)
                        .attr('y', y)
                        .style('font-size', `${LABEL_FONT_SIZE}px`)
                        .text(line);

                    y += lineIndex === labelLines.length - 1 ? LABEL_LINE_HEIGHT + 8 : LABEL_LINE_HEIGHT;
                });

                descriptionLines.forEach((line) => {
                    text
                        .append('tspan')
                        .attr('x', 0)
                        .attr('y', y)
                        .attr('fill-opacity', 0.72)
                        .style('font-size', `${DESCRIPTION_FONT_SIZE}px`)
                        .style('font-weight', 500)
                        .text(line);

                    y += DESCRIPTION_LINE_HEIGHT;
                });
            });
        },

        wrapSvgText(text, content, maxWidth, fontSize, maxLines) {
            const words = content.split(/\s+/).filter(Boolean);

            if (words.length === 0) {
                return [];
            }

            const measure = text
                .append('tspan')
                .attr('x', 0)
                .attr('y', -9999)
                .style('font-size', `${fontSize}px`)
                .style('visibility', 'hidden');

            const lines = [];
            let currentLine = words.shift() ?? '';

            words.forEach((word) => {
                const candidate = `${currentLine} ${word}`;
                measure.text(candidate);

                if (measure.node()?.getComputedTextLength() > maxWidth && currentLine !== '') {
                    lines.push(currentLine);
                    currentLine = word;
                    return;
                }

                currentLine = candidate;
            });

            if (currentLine) {
                lines.push(currentLine);
            }

            measure.remove();

            if (lines.length <= maxLines) {
                return lines;
            }

            const clamped = lines.slice(0, maxLines);
            clamped[maxLines - 1] = this.truncateSvgLine(text, clamped[maxLines - 1], maxWidth, fontSize);

            return clamped;
        },

        truncateSvgLine(text, content, maxWidth, fontSize) {
            const measure = text
                .append('tspan')
                .attr('x', 0)
                .attr('y', -9999)
                .style('font-size', `${fontSize}px`)
                .style('visibility', 'hidden');

            let truncated = content;
            measure.text(`${truncated}…`);

            while (truncated.length > 0 && (measure.node()?.getComputedTextLength() ?? 0) > maxWidth) {
                truncated = truncated.slice(0, -1).trimEnd();
                measure.text(`${truncated}…`);
            }

            measure.remove();

            return truncated ? `${truncated}…` : '…';
        },

        toggleLabel(node) {
            if (node.children) {
                return '−';
            }

            if (node._children) {
                return '+';
            }

            return '';
        },

        getBounds(nodes) {
            const minX = d3.min(nodes, (d) => d.x) ?? 0;
            const maxX = d3.max(nodes, (d) => d.x) ?? 0;
            const minY = d3.min(nodes, (d) => d.y) ?? 0;
            const maxY = d3.max(nodes, (d) => d.y) ?? 0;

            return {
                minX,
                maxX,
                minY,
                maxY,
            };
        },

        fitToView(bounds) {
            const width = this.$refs.diagram.clientWidth || 1200;
            const height = this.$refs.diagram.clientHeight || 700;
            const contentWidth = Math.max(bounds.maxY - bounds.minY + NODE_WIDTH + 160, 1);
            const contentHeight = Math.max(bounds.maxX - bounds.minX + NODE_HEIGHT + 160, 1);
            const scale = Math.min(width / contentWidth, height / contentHeight, 1);
            const x = (width - contentWidth * scale) / 2 - (bounds.minY - NODE_WIDTH / 2 - 80) * scale;
            const y = (height - contentHeight * scale) / 2 - (bounds.minX - NODE_HEIGHT / 2 - 80) * scale;

            this.svg
                .transition()
                .duration(DURATION)
                .call(this.zoomBehavior.transform, d3.zoomIdentity.translate(x, y).scale(scale));
        },

        switchTab(name) {
            this.renderTree(name);
        },

        expandAll() {
            if (!this.root) {
                return;
            }

            this.root.descendants().forEach((node) => {
                if (node._children) {
                    node.children = node._children;
                    node._children = null;
                }
            });

            this.update(this.root);
        },

        collapseAll() {
            if (!this.root) {
                return;
            }

            this.root.descendants().forEach((node) => {
                if (node.children && node.depth > 0) {
                    node._children = node.children;
                    node.children = null;
                }
            });

            this.update(this.root);
        },

        resetView() {
            if (!this.root) {
                return;
            }

            this.fitToView(this.getBounds(this.root.descendants()));
        },

        async exportPdf() {
            if (this.exporting || !this.root) {
                return;
            }

            this.exporting = true;

            try {
                const [{ jsPDF }, { svg2pdf }] = await Promise.all([
                    import('jspdf'),
                    import('svg2pdf.js'),
                ]);

                const savedState = new Map();
                this.root.descendants().forEach((node) => {
                    savedState.set(node.id, {
                        children: node.children,
                        _children: node._children,
                    });
                });

                const expandRecursive = (node) => {
                    if (node._children) {
                        node.children = node._children;
                        node._children = null;
                    }
                    node.children?.forEach(expandRecursive);
                };
                expandRecursive(this.root);

                this.update(this.root);
                await new Promise((resolve) => setTimeout(resolve, DURATION + 100));

                const nodes = this.root.descendants();
                const bounds = this.getBounds(nodes);

                const padding = 80;
                const contentWidth = bounds.maxY - bounds.minY + NODE_WIDTH + padding * 2;
                const contentHeight = bounds.maxX - bounds.minX + NODE_HEIGHT + padding * 2;
                const viewBoxX = bounds.minY - NODE_WIDTH / 2 - padding;
                const viewBoxY = bounds.minX - NODE_HEIGHT / 2 - padding;

                const svgNode = this.svg.node();
                const origViewBox = svgNode.getAttribute('viewBox');
                const origClass = svgNode.getAttribute('class');
                const origStyle = svgNode.getAttribute('style');
                const origGroupTransform = this.rootGroup.node().getAttribute('transform');

                svgNode.removeAttribute('class');
                svgNode.removeAttribute('style');
                svgNode.setAttribute('viewBox', `${viewBoxX} ${viewBoxY} ${contentWidth} ${contentHeight}`);
                svgNode.setAttribute('width', contentWidth);
                svgNode.setAttribute('height', contentHeight);
                svgNode.style.fontFamily = 'Inter, sans-serif';
                this.rootGroup.node().removeAttribute('transform');

                this.rootGroup.selectAll('.org-chart-toggle').attr('display', 'none');
                this.rootGroup.selectAll('.org-chart-node').each(function () {
                    this.style.removeProperty('cursor');
                });

                const fontData = await fetch('/fonts/inter/Inter.ttf').then((r) => r.arrayBuffer());
                const fontBase64 = btoa(
                    new Uint8Array(fontData).reduce((data, byte) => data + String.fromCharCode(byte), ''),
                );

                const doc = new jsPDF({
                    orientation: contentWidth > contentHeight ? 'landscape' : 'portrait',
                    unit: 'pt',
                    format: [contentWidth, contentHeight],
                });

                doc.addFileToVFS('Inter.ttf', fontBase64);
                doc.addFont('Inter.ttf', 'Inter', 'normal', 400);
                doc.addFont('Inter.ttf', 'Inter', 'normal', 500);
                doc.addFont('Inter.ttf', 'Inter', 'normal', 600);

                doc.setFillColor(38, 36, 32);
                doc.rect(0, 0, contentWidth, contentHeight, 'F');

                await svg2pdf(svgNode, doc, {
                    x: 0,
                    y: 0,
                    width: contentWidth,
                    height: contentHeight,
                });

                doc.save(`org-chart-${this.activeTree}.pdf`);

                if (origClass) {
                    svgNode.setAttribute('class', origClass);
                }
                svgNode.removeAttribute('style');
                if (origStyle) {
                    svgNode.setAttribute('style', origStyle);
                }
                svgNode.setAttribute('viewBox', origViewBox);
                svgNode.removeAttribute('width');
                svgNode.removeAttribute('height');
                if (origGroupTransform) {
                    this.rootGroup.node().setAttribute('transform', origGroupTransform);
                }
                this.rootGroup.selectAll('.org-chart-toggle').attr('display', null);

                this.root.descendants().forEach((node) => {
                    const saved = savedState.get(node.id);
                    if (saved) {
                        node.children = saved.children;
                        node._children = saved._children;
                    }
                });
                this.update(this.root);
            } catch (error) {
                console.error('PDF export failed:', error);
            } finally {
                this.exporting = false;
            }
        },
    }));
});
