define([], function () {
    require.config({
    paths: {
        'simditor': '../addons/simditor/js/simditor',
        'simple-module': '../addons/simditor/js/module',
        'simple-hotkeys': '../addons/simditor/js/hotkeys',
        'simple-uploader': '../addons/simditor/js/uploader',
        'dompurify': '../addons/simditor/js/dompurify',
    },
    shim: {
        'simditor': [
            'css!../addons/simditor/css/simditor.min.css',
        ]
    }
});
require(['form'], function (Form) {
    var _bindevent = Form.events.bindevent;
    Form.events.bindevent = function (form) {
        _bindevent.apply(this, [form]);
        if ($(Config.simditor.classname || '.editor', form).length > 0) {
            //修改上传的接口调用
            require(['upload', 'simditor', 'dompurify'], function (Upload, Simditor, DOMPurify) {
                var editor, mobileToolbar, toolbar;
                Simditor.locale = 'zh-CN';
                Simditor.list = {};
                toolbar = ['title', 'bold', 'italic', 'underline', 'strikethrough', 'fontScale', 'color', '|', 'ol', 'ul', 'blockquote', 'code', 'table', '|', 'link', 'image', 'hr', '|', 'indent', 'outdent', 'alignment'];
                mobileToolbar = ["bold", "underline", "strikethrough", "color", "ul", "ol"];

                // 添加 hook 过滤 iframe 来源
                DOMPurify.addHook('uponSanitizeElement', function (node, data, config) {
                    if (data.tagName === 'iframe') {
                        var allowedIframePrefixes = Config.nkeditor.allowiframeprefixs || [];
                        var src = node.getAttribute('src');

                        // 判断是否匹配允许的前缀
                        var isAllowed = false;
                        for (var i = 0; i < allowedIframePrefixes.length; i++) {
                            if (src && src.indexOf(allowedIframePrefixes[i]) === 0) {
                                isAllowed = true;
                                break;
                            }
                        }

                        if (!isAllowed) {
                            // 不符合要求则移除该节点
                            return node.parentNode.removeChild(node);
                        }

                        // 添加安全属性
                        node.setAttribute('allowfullscreen', '');
                        node.setAttribute('allow', 'fullscreen');
                    }
                });
                var purifyOptions = {
                    ADD_TAGS: ['iframe'],
                    FORCE_REJECT_IFRAME: false
                };

                $(Config.simditor.classname || '.editor', form).each(function () {
                    var id = $(this).attr("id");
                    editor = new Simditor({
                        textarea: this,
                        height: isNaN(Config.simditor.height) ? null : parseInt(Config.simditor.height),
                        minHeight: parseInt(Config.simditor.minHeight || 250),
                        toolbar: Config.simditor.toolbar || [],
                        mobileToolbar: Config.simditor.mobileToolbar || [],
                        toolbarFloat: parseInt(Config.simditor.toolbarFloat),
                        placeholder: Config.simditor.placeholder || '',
                        dompurify: {
                            enabled: Config.simditor.isdompurify,
                            options: purifyOptions
                        },
                        pasteImage: true,
                        defaultImage: Config.__CDN__ + '/assets/addons/simditor/images/image.png',
                        upload: {url: '/'},
                        allowedTags: ['div', 'br', 'span', 'a', 'img', 'b', 'strong', 'i', 'strike', 'u', 'font', 'p', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'h1', 'h2', 'h3', 'h4', 'hr'],
                        allowedAttributes: {
                            div: ['data-tpl', 'data-source', 'data-id'],
                            span: ['data-id']
                        },
                        allowedStyles: {
                            div: ['width', 'height', 'padding', 'background', 'color', 'display', 'justify-content', 'border', 'box-sizing', 'max-width', 'min-width', 'position', 'margin-left', 'bottom', 'left', 'margin', 'float'],
                            p: ['margin', 'color', 'height', 'line-height', 'position', 'width', 'border', 'bottom', 'float'],
                            span: ['text-decoration', 'color', 'margin-left', 'float', 'background', 'padding', 'margin-right', 'border-radius', 'font-size', 'border', 'float'],
                            img: ['vertical-align', 'width', 'height', 'object-fit', 'float', 'margin', 'float'],
                            a: ['text-decoration']
                        }
                    });
                    editor.uploader.on('beforeupload', function (e, file) {
                        Upload.api.send(file.obj, function (data) {
                            var url = Fast.api.cdnurl(data.url);
                            editor.uploader.trigger("uploadsuccess", [file, {success: true, file_path: url}]);
                        });
                        return false;
                    });
                    editor.on("blur", function () {
                        this.textarea.trigger("blur");
                    });
                    if (editor.opts.height) {
                        editor.body.css({height: editor.opts.height, 'overflow-y': 'auto'});
                    }
                    if (editor.opts.minHeight) {
                        editor.body.css({'min-height': editor.opts.minHeight});
                    }
                    Simditor.list[id] = editor;
                });
            });
        }
    }
});

});