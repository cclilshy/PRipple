<style>
    .cct-header {
        padding: 8px;
        height: 14px;
        display: flex;
        align-items: center;
        background-color: rgb(137, 187, 137);
        text-align: right;
    }

    .cct-ssc {
        padding: 0px 10px 10px 10px;
        display: inline-block;
        width: 100%;
        overflow: hidden;
        height: 168px;
    }

    .cct-ul {
        margin: 0px;
        position: absolute;
        padding: 0px;
        display: block;
        width: 100%;
        height: 158px;
        overflow-y: scroll;
    }

    .cct-span {
        margin-right: 10px;
        cursor: pointer;
    }

    .cct-li {
        display: inherit;
        border-bottom: 1px solid #d8d8d8;
        font-size: 14px;
    }

    .cct-active {
        font-weight: bold;
    }

    .cct-button {
        right: 0px;
        bottom: 0px;
        position: fixed;
        z-index: 9999;
        color: #fff;
        background-color: green;
        border: 0px;
    }

    .cct-panel {
        position: fixed;
        right: 0px;
        bottom: 0px;
        display: block;
        width: 100%;
        height: 188px;
        background-color: rgb(250, 250, 250);
    }
</style>
<button class="cct-button" onclick="shoDebugPanel()">Toggle Debug</button>
<div class="cct-panel" style="display: none;z-index: 9998;">
    <div class="cct-header">
        <span class="cct-span cct-active" name="General">General</span>
        <span class="cct-span" name="Files">Files</span>
        <span class="cct-span" name="Sql">Sql</span>
        <span class="cct-span" name="Input">Input</span>
    </div>
    <div class="cct-ssc">
        <div id="General">
            <ul class="cct-ul">
                <li class="cct-li">uri: {{ $general['uri'] }}</li>
                <li class="cct-li">file Count: {{ $general['fileCount'] }}</li>
                <li class="cct-li">runtime: {{ $general['timeLength'] }} (second)</li>
                <li class="cct-li">memory: {{ $general['memory'] }}</li>
                <li class="cct-li">max memory: {{ $general['maxMemory'] }}</li>
            </ul>
        </div>
        <div id="Files" style="display: none;">
            <ul class="cct-ul">
                <li class="cct-li">
                    @foreach($files as $item)
                <li class="cct-li">{{ $item }} ({{ filesize($item)/1000 }} Kb)</li>
                @endforeach
                </li>
            </ul>
        </div>
        <div id="Sql" style="display: none;">
            <ul class="cct-ul">
                @foreach($sqlps as $item)
                <li class="cct-li"> {{$item}} </li>
                @endforeach
            </ul>
        </div>
        <div id="Input" style="display: none;">
            <ul class="cct-ul">
                @foreach($gets as $key => $get)
                <li class="cct-li"> (GET) {{ $key }} : {{$get}}</li>
                @endforeach

                @foreach($posts as $key => $post)
                <li class="cct-li"> (POST) {{ $key }} : {{$post}} </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
<script>
    function shoDebugPanel() {
        let divElement = document.querySelector("div.cct-panel");
        if (divElement.style.display === "none") {
            divElement.style.display = "block";
            localStorage.setItem("ccpDebugPanelDisplayed", "true");
        } else {
            divElement.style.display = "none";
            localStorage.setItem("ccpDebugPanelDisplayed", "false");
        }
    }

    window.addEventListener("load", function () {
        let displayValue = localStorage.getItem("ccpDebugPanelDisplayed");
        if (displayValue === "true") {
            shoDebugPanel();
        }
    });
    let boldClassName = "cct-active";
    let spanElements = document.querySelectorAll("span");
    spanElements.forEach(element => {
        element.addEventListener("click", () => {
            element.classList.add(boldClassName);
            spanElements.forEach(otherElement => {
                if (otherElement !== element) {
                    otherElement.classList.remove(boldClassName);
                }
            });
            let elementId = element.getAttribute("name");
            document.getElementById(elementId).style.display = "block";
            let parentElement = document.getElementById(elementId).parentNode;
            let divElements = parentElement.querySelectorAll("div");
            divElements.forEach(divElement => {
                if (divElement.getAttribute("id") !== elementId) {
                    divElement.style.display = "none";
                }
            });
        });
    });
</script>