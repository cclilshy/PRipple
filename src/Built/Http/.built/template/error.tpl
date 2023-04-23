<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>A system error has occurred.</title>
    <style>
        pre code.hljs {
            display: block;
            overflow-x: auto;
            padding: 1em
        }

        code.hljs {
            padding: 3px 5px
        }

        .hljs {
            background: #f3f3f3;
            color: #444
        }

        .hljs-comment {
            color: #697070
        }

        .hljs-punctuation,
        .hljs-tag {
            color: #444a
        }

        .hljs-tag .hljs-attr,
        .hljs-tag .hljs-name {
            color: #444
        }

        .hljs-attribute,
        .hljs-doctag,
        .hljs-keyword,
        .hljs-meta .hljs-keyword,
        .hljs-name,
        .hljs-selector-tag {
            font-weight: 700
        }

        .hljs-deletion,
        .hljs-number,
        .hljs-quote,
        .hljs-selector-class,
        .hljs-selector-id,
        .hljs-string,
        .hljs-template-tag,
        .hljs-type {
            color: #800
        }

        .hljs-section,
        .hljs-title {
            color: #800;
            font-weight: 700
        }

        .hljs-link,
        .hljs-operator,
        .hljs-regexp,
        .hljs-selector-attr,
        .hljs-selector-pseudo,
        .hljs-symbol,
        .hljs-template-variable,
        .hljs-variable {
            color: #ab5656
        }

        .hljs-literal {
            color: #695
        }

        .hljs-addition,
        .hljs-built_in,
        .hljs-bullet,
        .hljs-code {
            color: #397300
        }

        .hljs-meta {
            color: #1f7199
        }

        .hljs-meta .hljs-string {
            color: #38a
        }

        .hljs-emphasis {
            font-style: italic
        }

        .hljs-strong {
            font-weight: 700
        }

        html {
            /* background-color: #f1f1f1; */
            margin: 0px;
        }

        body {
            margin: 10px;
            /* padding: 0px; */
            font-family: sans-serif;
        }

        .error-container {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin: 16px;
            overflow: hidden;
        }

        .error-title {
            font-size: 24px;
            color: #787878;
            background: #ff00001c;
            padding: 8px 0px 8px 8px;
            width: 100%;

        }


        .error-message {
            font-size: 16px;
            color: #787878;
            background: #ff00001c;
            padding: 8px 0px 8px 8px;
            width: 100%;
        }

        .error-file {
            font-size: 14px;
            /* margin: 8px 0; */
            background: #565656;
            padding: 8px;
            width: 100%;
            font-weight: bold;
            color: #fff;
        }

        .table-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 16px;

        }

        .table-title {
            width: 100px;
            font-weight: bold;
            color: #0074d9;

        }

        ul {
            list-style: none;
            margin: 0;
            padding: 8px;
            background-color: #ececec;
            border-radius: 4px;
            margin-right: 16px;
            margin-bottom: 16px;
        }

        li {
            display: flex;
            align-items: center;
            /* padding: 8px; */
        }

        li:nth-child(even) {
            background-color: #f2f2f2;
        }

        span {
            margin-left: 8px;
        }

        .line-highlight {
            background-color: #0000008a !important;
        }

        pre {
            /* padding: 0px !important; */
            margin: 0px !important;
            width: 100%;
        }

        .hljs-ln-line[data-line-number="{{$general['info']['errLine']}}"] {
            background: palegoldenrod;
            color: #ff4136;
        }
    </style>
</head>

<body>
<div class="error-container">
    <div class="error-title">{{ $general['info']['error'] }}</div>
    <div class="error-file">{{ $general['info']['errFile'] }}({{ $general['info']['errLine'] }})</div>
    <div id="detail">
        <pre><code id="errcode">{{ $general['info']['fileDescribe'] }}</code></pre>
        <div class="table-container">
            <ul>
                <li>Files</li>
                @foreach($files as $item)
                <li class="cct-li">{{ $item }} ({{ filesize($item)/1000 }} KB)</li>
                @endforeach
            </ul>
            @if(count(array_merge($gets,$posts)) > 0)
            <ul>
                <li>Input</li>
                @foreach($gets as $key => $get)
                <li class="cct-li"> (GET) {{ $key }} : {{$get}}</li>
                @endforeach

                @foreach($posts as $post)
                <li class="cct-li"> (POST) {{ $key }} : {{$post}} </li>
                @endforeach
            </ul>
            @endif
            <ul>
                <li>Base</li>
                <li class="cct-li">file Count: {{ $general['fileCount'] }}</li>
                <li class="cct-li">runtime: {{ $general['timeLength'] }} (second)</li>
                <li class="cct-li">memory: {{ $general['memory'] }}</li>
                <li class="cct-li">max memory: {{ $general['maxMemory'] }}</li>
            </ul>
            @if(count($sqlps) > 0)
            <ul>
                @foreach($sqlps as $item)
                <li class="cct-li"> {{$item}} </li>
                @endforeach
            </ul>
            @endif

        </div>
    </div>
</div>
<script src="/assets/js/Highlight.js"></script>
</body>
</html>