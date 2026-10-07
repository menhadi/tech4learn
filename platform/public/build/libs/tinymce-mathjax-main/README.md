# Reference from @dimakorotkov/tinymce-mathjax

# TinyMCE MathJax Plugin

This plugin using [MathJax](https://www.mathjax.org) library for rendering math font.

This plugin compatible with TinyMCE 5 and MathJax 3 using for case study.

## Install

### NPM:

```
npm i @peterbasksd/tinymce-mathjax --save
```

You can install mathjax and tinymce from npm

```
npm i mathjax --save
```

```
npm i tinymce --save
```

### Download

- [Latest build](https://github.com/peterbasksd/tinymce-mathjax/archive/master.zip)

## Usage

### TinyMCE editor

Configure your TinyMCE init settings by adding `external_plugins` and usage of `mathjax`:

```
tinymce.init({
  ...
  external_plugins: {'mathjax': '/your-path-to-plugin/@peterbasksd/tinymce-mathjax/plugin.min.js'},
  toolbar: 'mathjax',
  mathjax: {
    lib: '/path-to-mathjax/es5/tex-mml-chtml.js', //required path to mathjax
    //symbols: {start: '\\(', end: '\\)'}, //optional: mathjax symbols
    //className: "math-tex", //optional: mathjax element class
    //configUrl: '/your-path-to-plugin/@peterbasksd/tinymce-mathjax/config.js' //optional: mathjax config js
  }
});
```

add index.html

```
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.9/tinymce.min.js"></script>
    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
    <script src="http://cdnjs.cloudflare.com/ajax/libs/tinymce/5.10.9/jquery.tinymce.min.js"></script>
```
