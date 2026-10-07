<script src="{{ asset('ckeditor/ckeditor.js') }}" type="text/javascript"></script>

<script type="text/javascript">
    MathJax = {
        tex: {
            inlineMath: [
                ['$', '$'],
                ['\\(', '\\)']
            ]
        }
    };
</script>
<script type="text/javascript">

	// jscolor.presets.default = {
    //     format: 'hexa'
    // };

    function ckeditor_initialize(type = '', note_id = false) {
        const CKEDITOR_CONFIG = {
            extraPlugins: 'mathjax,justify,font',
            mathJaxLib: "{{ asset('MathJax') }}/MathJax.js?config=TeX-AMS_HTML",
            height: 100,
            removeButtons: 'Paste,PasteText,PasteFromWord,Anchor,Image,Font',
            removePlugins: 'scayt',
            format_tags: 'p;h1;h2;h3;h4;h5;h6',
        };

        const requiredFieldWarning = 'This field is required.';

        let requiredEditorIds = ['description', 'shortDescription'];
        let EditorIds = [];
        @if(true)
            //EditorIds.push('e');
        @endif
        let removeEditorIds = [];

        if (type == 2) {
            removeEditorIds.push(...EditorIds);
        } else if (type == 1 || type == '') {
            requiredEditorIds.push(...EditorIds);
        }

        destroyEditors([...requiredEditorIds, ...removeEditorIds]);
        initializeEditors(requiredEditorIds, CKEDITOR_CONFIG, requiredFieldWarning);

        initializeEditors(removeEditorIds, CKEDITOR_CONFIG, requiredFieldWarning, true);

        if (note_id) {
            const noteIds = ['note'];
            destroyEditors([noteIds]);
            initializeEditors(noteIds, CKEDITOR_CONFIG);
        }


        if (CKEDITOR.env.ie && CKEDITOR.env.version == 8) {
            document.getElementById('ie8-warning').className = 'tip alert';
        }
    }

    function initializeEditors(editorIds, config, warning = '', removeListeners = false) {
        editorIds.forEach(id => {
            const editor = CKEDITOR.replace(id, config);
            if (removeListeners && editor) {
                editor.removeAllListeners();
            } else if (warning) {
                editor.on('required', evt => {
                    editor.showNotification(warning, 'warning');
                    evt.cancel();
                });
            }
        });
    }

    function destroyEditors(editorIds) {
        editorIds.forEach(id => {
            const editor = CKEDITOR.instances[id];
            if (editor) {
                editor.destroy();
            }
        });
    }

    // function createCkeditor() {

    //     for (let equation_editor in CKEDITOR.instances) {
    //         CKEDITOR.instances[equation_editor].destroy();
    //     }
    //     CKEDITOR.replaceAll(function(textarea, config) {
    //         if (textarea.getAttribute('data-editor') == 'true') {

    //             var height = 100;

    //             if(textarea.getAttribute('data-height')) {
    //                 height = textarea.getAttribute('data-height');
    //             }

    //             config.mathJaxLib = "{{ asset('MathJax') }}/MathJax.js?config=TeX-AMS_HTML";
    //             config.extraPlugins = 'mathjax,justify';
    //             config.height = height;
    //             return true;
    //         }
    //         return false;
    //     });

    //     // inline editors
    //     let elements = CKEDITOR.document.find('.editor-inline'),
    //         i = 0,
    //         element;
    //     while ((element = elements.getItem(i++))) {
    //         CKEDITOR.inline(element, {
    //             mathJaxLib: "{{ asset('MathJax') }}/MathJax.js?config=TeX-AMS_HTML",
    //             extraPlugins: 'mathjax,justify',
    //             readOnly: true,
    //         });
    //     }
    // }

    function createCkeditor() {
        // Destroy existing CKEditor instances before reinitializing
        Object.keys(CKEDITOR.instances).forEach(instance => {
            if (CKEDITOR.instances[instance]) {
                CKEDITOR.instances[instance].destroy(true);
            }
        });

        // Reinitialize CKEditor for all textareas with 'data-editor' attribute
        document.querySelectorAll("textarea[data-editor='true']").forEach(textarea => {
            let height = textarea.getAttribute('data-height') || 100;

            CKEDITOR.replace(textarea, {
                mathJaxLib: "{{ asset('MathJax') }}/MathJax.js?config=TeX-AMS_HTML",
                extraPlugins: 'mathjax,justify,font',
                height: height,
                extraAllowedContent: 'span(*) div(*)',
                enterMode: CKEDITOR.ENTER_BR, // Prevents auto-wrapping in <p>
                // autoParagraph: false,
                autoParagraph: true,
                fillEmptyBlocks: false,
                allowedContent: true, // Allows all content without restriction
                format_tags: 'p;h1;h2;h3;h4;h5;h6',
                removeButtons: 'Font',
            });
        });

        // Initialize CKEditor inline editors (if needed)
        document.querySelectorAll(".editor-inline").forEach(element => {
            CKEDITOR.inline(element, {
                mathJaxLib: "{{ asset('MathJax') }}/MathJax.js?config=TeX-AMS_HTML",
                extraPlugins: 'mathjax,justify,font',
                readOnly: true,
                format_tags: 'p;h1;h2;h3;h4;h5;h6',
                removeButtons: 'Font',
            });
        });
    }

    const initCkeditor = () => {
        // Ensure CKEditor updates the underlying form field before submission
        if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances) {
            for (let instance in CKEDITOR.instances) {
                if (CKEDITOR.instances.hasOwnProperty(instance)) {
                    CKEDITOR.instances[instance].updateElement();
                }
            }
        }

        // if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances) {
        //     Object.keys(CKEDITOR.instances).forEach(instance => {
        //         CKEDITOR.instances[instance].updateElement();
        //     });
        // }
    }
</script>