document.addEventListener("DOMContentLoaded", function() {
    const container = document.getElementById("artifact-marketplace-container");
    if (!container) return;

    fetch(bitcoreData.restUrl + "bitcore/v1/artifacts/marketplace")
        .then(r => r.json())
        .then(data => {
            container.innerHTML = "<h2>Artifact Marketplace</h2><pre>" + JSON.stringify(data, null, 2) + "</pre>";
        })
        .catch(err => { container.innerHTML = "Error: " + err.message; });
});
