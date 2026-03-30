document.addEventListener("DOMContentLoaded", function() {
    const container = document.getElementById("artifact-builder-container");
    if (!container) return;

    fetch(bitcoreData.restUrl + "bitcore/v1/artifacts/builder")
        .then(r => r.json())
        .then(data => {
            container.innerHTML = "<h2>Artifact Builder</h2><pre>" + JSON.stringify(data, null, 2) + "</pre>";
        })
        .catch(err => { container.innerHTML = "Error: " + err.message; });
});
