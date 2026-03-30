document.addEventListener("DOMContentLoaded", function() {
    const container = document.getElementById("artifact-editor-container");
    if (!container) return;

    fetch(bitcoreData.restUrl + "bitcore/v1/artifacts/editor")
        .then(r => r.json())
        .then(data => {
            container.innerHTML = "<h2>Artifact Editor</h2><pre>" + JSON.stringify(data, null, 2) + "</pre>";
        })
        .catch(err => { container.innerHTML = "Error: " + err.message; });
});
