import { Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';
import { Markdown } from 'tiptap-markdown';

const localImagePattern = /^(?:\.\/)?[A-Za-z0-9._-]+\.(?:jpe?g|png|gif|svg|webp)$/i;
const slidePattern = /^!\[([^\]\r\n]+)\]\(([^)\r\n]+)\)(?:\r?\n+[ \t]*\*([^\r\n]*)\*)?[ \t]*$/;

export function parseCarouselBody(body) {
    const parts = body.trim().split(/^[ \t]*<!--\s*slide\s*-->[ \t]*$/gmi);
    if (parts.length < 2) return null;

    const slides = [];
    for (const part of parts) {
        const match = part.trim().match(slidePattern);
        if (!match || !match[1].trim() || !localImagePattern.test(match[2].trim())) return null;
        slides.push({
            alt: match[1].trim(),
            src: match[2].trim().replace(/^\.\//, ''),
            caption: (match[3] || '').trim(),
        });
    }
    return slides;
}

function serializeCarousel(slides) {
    const body = slides.map((slide) => {
        const image = `![${slide.alt || ''}](${slide.src || ''})`;
        return slide.caption ? `${image}\n*${slide.caption}*` : image;
    }).join('\n<!-- slide -->\n');
    return `\`\`\`carousel\n${body}\n\`\`\``;
}

function escapeAttribute(value) {
    return value.replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
}

export function parseWirelistBody(body) {
    let data;
    try {
        data = JSON.parse(body.trim());
    } catch {
        return null;
    }
    if (!data || typeof data.title !== 'string' || !data.title.trim() || !Array.isArray(data.variants) || !data.variants.length) return null;
    for (const variant of data.variants) {
        if (!variant || typeof variant.id !== 'string' || !/^[A-Za-z0-9._-]+$/.test(variant.id)
            || typeof variant.label !== 'string' || !variant.label.trim()
            || !Array.isArray(variant.groups) || !variant.groups.length) return null;
        for (const group of variant.groups) {
            if (!group || typeof group.label !== 'string' || !group.label.trim()
                || !Array.isArray(group.rows) || !group.rows.length) return null;
            for (const row of group.rows) {
                if (!row || ['pin', 'signal', 'path'].some((key) => typeof row[key] !== 'string' || !row[key].trim())
                    || (row.note !== undefined && typeof row.note !== 'string')) return null;
            }
        }
    }
    return data;
}

function serializeWirelist(wirelist) {
    return `\`\`\`wirelist\n${JSON.stringify(wirelist, null, 2)}\n\`\`\``;
}

function wirelistNodeView({ node: initialNode, view, getPos }) {
    let node = initialNode;
    const dom = document.createElement('div');
    dom.className = 'ed-wirelist-node';
    dom.contentEditable = 'false';

    const element = (tag, className, text) => {
        const el = document.createElement(tag);
        if (className) el.className = className;
        if (text !== undefined) el.textContent = text;
        return el;
    };
    const input = (value, placeholder, label, change) => {
        const el = document.createElement('input');
        el.type = 'text';
        el.value = value || '';
        el.placeholder = placeholder;
        el.setAttribute('aria-label', label);
        el.addEventListener('change', () => change(el.value.replace(/[\r\n]/g, ' ').trim()));
        return el;
    };
    const button = (text, click, className = '') => {
        const el = element('button', className, text);
        el.type = 'button';
        el.addEventListener('click', click);
        return el;
    };
    const update = (wirelist) => {
        const pos = getPos();
        if (typeof pos === 'number') view.dispatch(view.state.tr.setNodeMarkup(pos, undefined, { wirelist }));
    };
    const clone = () => structuredClone(node.attrs.wirelist);

    const render = () => {
        dom.replaceChildren();
        const wirelist = node.attrs.wirelist;
        const heading = element('div', 'ed-wirelist-heading');
        heading.append(element('strong', '', 'Searchable wirelist'));
        heading.append(input(wirelist.title, 'Wirelist title', 'Wirelist title', (value) => {
            const next = clone();
            next.title = value;
            update(next);
        }));
        dom.append(heading);

        const variants = element('div', 'ed-wirelist-variants');
        wirelist.variants.forEach((variant, variantIndex) => {
            const variantCard = element('section', 'ed-wirelist-variant');
            const variantHead = element('div', 'ed-wirelist-cardhead');
            variantHead.append(
                input(variant.id, 'ecu-id', `Variant ${variantIndex + 1} identifier`, (value) => {
                    const next = clone();
                    next.variants[variantIndex].id = value.replace(/[^A-Za-z0-9._-]/g, '-');
                    update(next);
                }),
                input(variant.label, 'ECU family label', `Variant ${variantIndex + 1} label`, (value) => {
                    const next = clone();
                    next.variants[variantIndex].label = value;
                    update(next);
                }),
                button('Remove ECU', () => {
                    if (wirelist.variants.length <= 1) return;
                    const next = clone();
                    next.variants.splice(variantIndex, 1);
                    update(next);
                }, 'is-danger'),
            );
            variantCard.append(variantHead);

            variant.groups.forEach((group, groupIndex) => {
                const groupCard = element('div', 'ed-wirelist-group');
                const groupHead = element('div', 'ed-wirelist-cardhead');
                groupHead.append(
                    input(group.label, 'Component group', `Component group ${groupIndex + 1}`, (value) => {
                        const next = clone();
                        next.variants[variantIndex].groups[groupIndex].label = value;
                        update(next);
                    }),
                    button('Remove group', () => {
                        if (variant.groups.length <= 1) return;
                        const next = clone();
                        next.variants[variantIndex].groups.splice(groupIndex, 1);
                        update(next);
                    }, 'is-danger'),
                );
                groupCard.append(groupHead);

                const rows = element('div', 'ed-wirelist-rows');
                group.rows.forEach((row, rowIndex) => {
                    const rowEl = element('div', 'ed-wirelist-row');
                    ['pin', 'signal', 'path', 'note'].forEach((key) => rowEl.append(input(
                        row[key] || '',
                        key === 'note' ? 'Optional note' : key,
                        `${group.label} row ${rowIndex + 1} ${key}`,
                        (value) => {
                            const next = clone();
                            next.variants[variantIndex].groups[groupIndex].rows[rowIndex][key] = value;
                            update(next);
                        },
                    )));
                    rowEl.append(
                        button('Up', () => {
                            if (rowIndex === 0) return;
                            const next = clone();
                            const nextRows = next.variants[variantIndex].groups[groupIndex].rows;
                            [nextRows[rowIndex - 1], nextRows[rowIndex]] = [nextRows[rowIndex], nextRows[rowIndex - 1]];
                            update(next);
                        }),
                        button('Down', () => {
                            if (rowIndex === group.rows.length - 1) return;
                            const next = clone();
                            const nextRows = next.variants[variantIndex].groups[groupIndex].rows;
                            [nextRows[rowIndex + 1], nextRows[rowIndex]] = [nextRows[rowIndex], nextRows[rowIndex + 1]];
                            update(next);
                        }),
                        button('Remove', () => {
                            if (group.rows.length <= 1) return;
                            const next = clone();
                            next.variants[variantIndex].groups[groupIndex].rows.splice(rowIndex, 1);
                            update(next);
                        }, 'is-danger'),
                    );
                    rows.append(rowEl);
                });
                groupCard.append(rows, button('Add connection', () => {
                    const next = clone();
                    next.variants[variantIndex].groups[groupIndex].rows.push({ pin: '', signal: '', path: '', note: '' });
                    update(next);
                }));
                variantCard.append(groupCard);
            });
            variantCard.append(button('Add component group', () => {
                const next = clone();
                next.variants[variantIndex].groups.push({
                    label: 'Component',
                    rows: [{ pin: '', signal: '', path: '', note: '' }],
                });
                update(next);
            }));
            variants.append(variantCard);
        });
        dom.append(variants);

        const footer = element('div', 'ed-wirelist-footer');
        footer.append(
            button('Add ECU family', () => {
                const next = clone();
                next.variants.push({
                    id: `ecu-${next.variants.length + 1}`,
                    label: 'ECU family',
                    groups: [{ label: 'Component', rows: [{ pin: '', signal: '', path: '', note: '' }] }],
                });
                update(next);
            }),
            button('Delete wirelist', () => {
                const pos = getPos();
                if (typeof pos === 'number') view.dispatch(view.state.tr.delete(pos, pos + node.nodeSize));
            }, 'is-danger'),
        );
        dom.append(footer);
    };
    render();

    return {
        dom,
        update(nextNode) {
            if (nextNode.type !== node.type) return false;
            node = nextNode;
            render();
            return true;
        },
        stopEvent: () => true,
        ignoreMutation: () => true,
    };
}

export const ArticleWirelist = Node.create({
    name: 'articleWirelist',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes() {
        return { wirelist: { default: null } };
    },
    parseHTML() {
        return [{
            tag: 'div[data-wirelist-node]',
            getAttrs: (element) => {
                try {
                    return { wirelist: JSON.parse(element.getAttribute('data-wirelist') || 'null') };
                } catch {
                    return false;
                }
            },
        }];
    },
    renderHTML({ node }) {
        return ['div', { 'data-wirelist-node': '', 'data-wirelist': JSON.stringify(node.attrs.wirelist) }];
    },
    addStorage() {
        return {
            markdown: {
                serialize(state, node) {
                    state.write(serializeWirelist(node.attrs.wirelist));
                    state.closeBlock(node);
                },
                parse: {
                    setup(markdownit) {
                        const fallback = markdownit.renderer.rules.fence
                            || ((tokens, idx, options, env, self) => self.renderToken(tokens, idx, options));
                        markdownit.renderer.rules.fence = (tokens, idx, options, env, self) => {
                            const token = tokens[idx];
                            if (token.info.trim() !== 'wirelist') return fallback(tokens, idx, options, env, self);
                            const wirelist = parseWirelistBody(token.content);
                            if (!wirelist) return fallback(tokens, idx, options, env, self);
                            return `<div data-wirelist-node data-wirelist="${escapeAttribute(JSON.stringify(wirelist))}"></div>`;
                        };
                    },
                },
            },
        };
    },
    addNodeView() {
        return wirelistNodeView;
    },
});

export const ArticleImage = Node.create({
    name: 'image',
    inline: true,
    group: 'inline',
    atom: true,
    draggable: true,
    addAttributes() {
        return {
            src: { default: null },
            alt: { default: null },
            title: { default: null },
        };
    },
    parseHTML() {
        return [{ tag: 'img[src]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['img', mergeAttributes(HTMLAttributes)];
    },
});

function carouselNodeView({ node: initialNode, view, getPos, extension }) {
    let node = initialNode;
    let assets = [];
    const dom = document.createElement('div');
    dom.className = 'ed-carousel-node';
    dom.contentEditable = 'false';

    const assetUrl = (src) => {
        const found = assets.find((asset) => asset.name === src);
        if (found) return found.url;
        const base = typeof extension.options.assetBase === 'function'
            ? extension.options.assetBase()
            : extension.options.assetBase;
        return base && src ? `${base.replace(/\/$/, '')}/${src.replace(/^\.\//, '')}` : '';
    };

    const updateSlides = (slides) => {
        const pos = getPos();
        if (typeof pos !== 'number') return;
        view.dispatch(view.state.tr.setNodeMarkup(pos, undefined, { slides }));
    };

    const element = (tag, className, text) => {
        const el = document.createElement(tag);
        if (className) el.className = className;
        if (text !== undefined) el.textContent = text;
        return el;
    };

    const option = (label, value, selected = false) => {
        const el = document.createElement('option');
        el.textContent = label;
        el.value = value;
        el.selected = selected;
        return el;
    };

    const render = () => {
        dom.replaceChildren();
        const heading = element('div', 'ed-carousel-heading');
        heading.append(element('strong', '', 'Image carousel'), element('span', '', `${node.attrs.slides.length} slides`));
        dom.append(heading);

        const list = element('div', 'ed-carousel-slides');
        node.attrs.slides.forEach((slide, index) => {
            const card = element('section', 'ed-carousel-slide');
            const preview = element('div', 'ed-carousel-preview');
            const url = assetUrl(slide.src);
            if (url) {
                const img = document.createElement('img');
                img.src = url;
                img.alt = slide.alt || '';
                preview.append(img);
            } else {
                preview.append(element('span', '', 'Choose or upload an image'));
            }

            const fields = element('div', 'ed-carousel-fields');
            const select = document.createElement('select');
            select.setAttribute('aria-label', `Image for slide ${index + 1}`);
            select.append(option('Choose an image...', '', !slide.src));
            assets.forEach((asset) => select.append(option(asset.name, asset.name, asset.name === slide.src)));
            select.addEventListener('change', () => {
                const slides = structuredClone(node.attrs.slides);
                slides[index].src = select.value;
                updateSlides(slides);
            });

            const alt = document.createElement('input');
            alt.type = 'text';
            alt.value = slide.alt || '';
            alt.placeholder = 'Required image alt text';
            alt.setAttribute('aria-label', `Alt text for slide ${index + 1}`);
            alt.addEventListener('change', () => {
                const slides = structuredClone(node.attrs.slides);
                slides[index].alt = alt.value.replace(/[\r\n]/g, ' ').trim();
                updateSlides(slides);
            });

            const caption = document.createElement('input');
            caption.type = 'text';
            caption.value = slide.caption || '';
            caption.placeholder = 'Optional caption';
            caption.setAttribute('aria-label', `Caption for slide ${index + 1}`);
            caption.addEventListener('change', () => {
                const slides = structuredClone(node.attrs.slides);
                slides[index].caption = caption.value.replace(/[\r\n]/g, ' ').trim();
                updateSlides(slides);
            });

            const upload = element('button', 'ed-carousel-upload', 'Upload image');
            upload.type = 'button';
            const picker = document.createElement('input');
            picker.type = 'file';
            picker.accept = 'image/png,image/jpeg,image/gif,image/webp';
            picker.hidden = true;
            upload.addEventListener('click', () => picker.click());
            picker.addEventListener('change', async () => {
                const file = picker.files?.[0];
                if (!file || !extension.options.uploadImage) return;
                upload.disabled = true;
                upload.textContent = 'Uploading...';
                try {
                    const asset = await extension.options.uploadImage(file, assets.map((item) => item.name));
                    if (asset) {
                        assets = await extension.options.getAssets();
                        const slides = structuredClone(node.attrs.slides);
                        slides[index].src = asset.name;
                        updateSlides(slides);
                    }
                } finally {
                    upload.disabled = false;
                    upload.textContent = 'Upload image';
                    picker.value = '';
                }
            });

            fields.append(select, upload, picker, alt, caption);

            const actions = element('div', 'ed-carousel-slide-actions');
            const up = element('button', '', 'Up');
            up.type = 'button';
            up.disabled = index === 0;
            up.addEventListener('click', () => {
                const slides = structuredClone(node.attrs.slides);
                [slides[index - 1], slides[index]] = [slides[index], slides[index - 1]];
                updateSlides(slides);
            });
            const down = element('button', '', 'Down');
            down.type = 'button';
            down.disabled = index === node.attrs.slides.length - 1;
            down.addEventListener('click', () => {
                const slides = structuredClone(node.attrs.slides);
                [slides[index + 1], slides[index]] = [slides[index], slides[index + 1]];
                updateSlides(slides);
            });
            const remove = element('button', 'is-danger', 'Remove');
            remove.type = 'button';
            remove.disabled = node.attrs.slides.length <= 2;
            remove.addEventListener('click', () => {
                const slides = structuredClone(node.attrs.slides);
                slides.splice(index, 1);
                updateSlides(slides);
            });
            actions.append(up, down, remove);
            card.append(preview, fields, actions);
            list.append(card);
        });
        dom.append(list);

        const footer = element('div', 'ed-carousel-footer');
        const add = element('button', '', 'Add slide');
        add.type = 'button';
        add.addEventListener('click', () => updateSlides([...structuredClone(node.attrs.slides), { src: '', alt: '', caption: '' }]));
        const removeCarousel = element('button', 'is-danger', 'Delete carousel');
        removeCarousel.type = 'button';
        removeCarousel.addEventListener('click', () => {
            const pos = getPos();
            if (typeof pos === 'number') view.dispatch(view.state.tr.delete(pos, pos + node.nodeSize));
        });
        footer.append(add, removeCarousel);
        dom.append(footer);
    };

    Promise.resolve(extension.options.getAssets?.() || []).then((result) => {
        assets = result || [];
        render();
    });
    render();

    return {
        dom,
        update(nextNode) {
            if (nextNode.type !== node.type) return false;
            node = nextNode;
            render();
            return true;
        },
        stopEvent: () => true,
        ignoreMutation: () => true,
    };
}

export const ArticleCarousel = Node.create({
    name: 'articleCarousel',
    group: 'block',
    atom: true,
    draggable: true,
    addOptions() {
        return {
            assetBase: '',
            getAssets: async () => [],
            uploadImage: null,
        };
    },
    addAttributes() {
        return {
            slides: { default: [] },
        };
    },
    parseHTML() {
        return [{
            tag: 'div[data-carousel-node]',
            getAttrs: (element) => {
                try {
                    return { slides: JSON.parse(element.getAttribute('data-slides') || '[]') };
                } catch {
                    return false;
                }
            },
        }];
    },
    renderHTML({ node }) {
        return ['div', { 'data-carousel-node': '', 'data-slides': JSON.stringify(node.attrs.slides) }];
    },
    addStorage() {
        return {
            markdown: {
                serialize(state, node) {
                    state.write(serializeCarousel(node.attrs.slides));
                    state.closeBlock(node);
                },
                parse: {
                    setup(markdownit) {
                        const fallback = markdownit.renderer.rules.fence
                            || ((tokens, idx, options, env, self) => self.renderToken(tokens, idx, options));
                        markdownit.renderer.rules.fence = (tokens, idx, options, env, self) => {
                            const token = tokens[idx];
                            if (token.info.trim() !== 'carousel') return fallback(tokens, idx, options, env, self);
                            const slides = parseCarouselBody(token.content);
                            if (!slides) return fallback(tokens, idx, options, env, self);
                            return `<div data-carousel-node data-slides="${escapeAttribute(JSON.stringify(slides))}"></div>`;
                        };
                    },
                },
            },
        };
    },
    addNodeView() {
        return carouselNodeView;
    },
});

export function createEditorExtensions(carouselOptions = {}) {
    return [
        StarterKit,
        ArticleImage,
        ArticleCarousel.configure(carouselOptions),
        ArticleWirelist,
        TableKit.configure({ table: { resizable: false } }),
        Markdown.configure({ html: true, tightLists: true, linkify: false, breaks: false }),
    ];
}
