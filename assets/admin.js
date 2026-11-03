(function () {
  "use strict";

  var settings = window.cbpYeastMigrate;
  var button = document.getElementById("cbp-ym-run");

  if (!settings || !button) {
    return;
  }

  var status = document.getElementById("cbp-ym-status");
  var table = document.getElementById("cbp-ym-results");
  var body = table.querySelector("tbody");
  var running = false;

  function cell(row, content) {
    var td = document.createElement("td");

    if (typeof content === "string") {
      td.textContent = content;
    } else {
      content.forEach(function (line) {
        var div = document.createElement("div");
        div.textContent = line;
        td.appendChild(div);
      });
    }

    row.appendChild(td);
  }

  function addRow(item) {
    var tr = document.createElement("tr");
    var changes = item.changes.map(function (change) {
      var value = change.value.length > 80 ? change.value.slice(0, 80) + "…" : change.value;
      return change.field + ": " + value;
    });

    cell(tr, String(item.id));
    cell(tr, item.title);
    cell(tr, changes);
    cell(tr, item.notes);
    body.appendChild(tr);
  }

  function post(after, dryRun, overwrite) {
    var data = new FormData();

    data.append("action", "cbp_yeast_migrate_batch");
    data.append("nonce", settings.nonce);
    data.append("after", String(after));
    data.append("dry_run", dryRun ? "1" : "");
    data.append("overwrite", overwrite ? "1" : "");

    return fetch(settings.ajaxUrl, { method: "POST", credentials: "same-origin", body: data }).then(function (response) {
      return response.json();
    });
  }

  button.addEventListener("click", function () {
    if (running) {
      return;
    }

    var dryRun = document.getElementById("cbp-ym-dry").checked;
    var overwrite = document.getElementById("cbp-ym-overwrite").checked;
    var seen = 0;
    var changed = 0;

    if (!dryRun && !window.confirm("Write Yoast values into Yeast now? Yoast's own data is not changed.")) {
      return;
    }

    running = true;
    button.disabled = true;
    body.innerHTML = "";
    table.style.display = "";

    function step(after) {
      post(after, dryRun, overwrite)
        .then(function (response) {
          if (!response.success) {
            throw new Error("Request refused");
          }

          response.data.rows.forEach(function (item) {
            seen += 1;
            changed += item.changes.length ? 1 : 0;
            addRow(item);
          });

          status.textContent = seen + " of " + settings.total + " checked";

          if (response.data.done) {
            status.textContent = (dryRun ? "Dry run complete: " : "Done: ") + changed + " of " + seen + " posts " + (dryRun ? "would change." : "updated.");
            running = false;
            button.disabled = false;
            return;
          }

          step(response.data.last_id);
        })
        .catch(function (error) {
          status.textContent = "Stopped: " + error.message;
          running = false;
          button.disabled = false;
        });
    }

    step(0);
  });
})();
