document.addEventListener("DOMContentLoaded", function() {
    const container = document.getElementById("artifact-pricing-container");
    if (!container) return;

    fetch(bitcoreData.restUrl + "bitcore/v1/artifacts/pricing")
        .then(r => r.json())
        .then(data => {
            container.innerHTML = "<h2>Artifact Pricing</h2><pre>" + JSON.stringify(data, null, 2) + "</pre>";
        })
        .catch(err => { container.innerHTML = "Error: " + err.message; });
});
