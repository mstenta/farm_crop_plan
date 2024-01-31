(async function (drupalSettings) {

  // Instantiate gantt chart.
  var gantt = new SvelteGantt({
    // target a DOM element
    target: document.getElementById('timeline'),
    // svelte-gantt options
    props: {
      target: document.getElementById('timeline'),
      from: Date.now()-86400,
      to: Date.now(),
      fitWidth: true,
      columnUnit: 'week',
      columnOffset: 1,
      rowHeight: 35,
      rowPadding: 4,
      tableHeaders: [{ title: 'Label', property: 'label', width: 140, type: 'tree' }],
      tableWidth: 240,
      ganttTableModules: [SvelteGanttTable],
      headers: [
        {unit: 'year', format: 'YYYY'},
        {unit: 'month', format: 'MM'},
      ],
      rows: [
        {id: 'first', label: 'Loading...'}
      ],
      taskElementHook: (node, task) => {
        let popup;
        function onHover() {
          popup = createPopup(task, node);
        }
        function onLeave() {
          if(popup) {
            popup.remove();
          }
        }
        node.addEventListener('mouseenter', onHover);
        node.addEventListener('mouseleave', onLeave);
        return {
          destroy() {
            node.removeEventListener('mouseenter', onHover);
            node.removeEventListener('mouseleave', onLeave);
          }
        }
      },
    }
  });

  function createPopup(task, node) {
    const rect = node.getBoundingClientRect();
    const div = document.createElement('div');
    div.className = 'sg-popup';
    div.innerHTML = `
            <div class="sg-popup-title">${task.label}</div>
            <div>Test</div>
            <div class="sg-popup-item">
                <div class="sg-popup-item-label">From:</div>
                <div class="sg-popup-item-value">${new Date(task.from).toLocaleTimeString()}</div>
            </div>
            <div class="sg-popup-item">
                <div class="sg-popup-item-label">To:</div>
                <div class="sg-popup-item-value">${new Date(task.to).toLocaleTimeString()}</div>
            </div>
        `;
    div.style.position = 'absolute';
    div.style.top = `${rect.bottom + 5}px`;
    div.style.left = `${rect.left + rect.width / 2}px`;

    if (task?.entityType == 'log') {
      div.innerHTML = `
            <div class="sg-popup-title">${task.label}</div>
            <div>${task.entityBundle} ${task.entityType}: ${task.entityId}</div>
            <div>Timestamp: ${new Date(task.from).toLocaleDateString()}</div>
        `;
    }

    if (task?.stage) {
      div.innerHTML = `
            <div class="sg-popup-title">${task.label}</div>
            <div>Stage: ${task.stage}</div>
            <div class="sg-popup-item">
                <div class="sg-popup-item-label">From:</div>
                <div class="sg-popup-item-value">${new Date(task.from).toLocaleDateString()}</div>
            </div>
            <div class="sg-popup-item">
                <div class="sg-popup-item-label">To:</div>
                <div class="sg-popup-item-value">${new Date(task.to).toLocaleDateString()}</div>
            </div>
        `;
    }

    document.body.appendChild(div);
    return div;
  }

  // Open entity page on click.
  gantt.api.tasks.on.select((task) => {
    task = task[0];
    if (task.model?.entityType) {
      var ajaxSettings = {
        url: `/${task.model.entityType}/${task.model.entityId}`,
        dialogType: 'dialog',
        dialogRenderer: 'off_canvas',
      };
      var myAjaxObject = Drupal.ajax(ajaxSettings);
      myAjaxObject.execute();
    } else {
      let dialog = document.getElementById('drupal-off-canvas');
      if (dialog) {
        Drupal.dialog(dialog, {}).close();
      }
    }
  });

  // Open edit form on double click.
  gantt.api.tasks.on.dblclicked((task) => {
    task = task[0];
    if (task.model?.entityType) {
      var ajaxSettings = {
        url: `/${task.model.entityType}/${task.model.entityId}/edit?redirect=${window.location.href}`,
        dialogType: 'dialog',
        dialogRenderer: 'off_canvas',
      };
      var myAjaxObject = Drupal.ajax(ajaxSettings);
      myAjaxObject.execute();
    } else {
      let dialog = document.getElementById('drupal-off-canvas');
      if (dialog) {
        Drupal.dialog(dialog, {}).close();
      }
    }
  });

  // Build a url to the plan timeline API.
  const url = new URL('plan/2/timeline/plant-type', window.location.origin + drupalSettings.path.baseUrl);
  const response = fetch(url)
    .then(res => res.json())
    .then(data => {

      // Keep track of first/last timestamps.
      let first = null;
      let last = null;

      // Build new list of rows/tasks.
      const rows = [];
      const tasks = [];

      // Build.
      for (let rowId in data.plant_type) {
        let plantType = data.plant_type[rowId];
        let row= {
          id: rowId,
          label: plantType.label,
          headerHtml: plantType.link,
          children: [],
          expanded: true,
          classes: ['row-plant-type'],
        };

        for (let plantId in plantType.plants) {
          let plant = plantType.plants[plantId];
          let assetRowId = `asset-${plantId}`;
          row.children.push({id: assetRowId, label: plant.label, headerHtml: plant.link, classes: ['row-asset']})
          plant.stages.forEach((stage) => {

            // Skip locations.
            if (stage.type == 'location') {
              return;
            }

            // Update start/end.
            let from = stage.start * 1000;
            let to = stage.end * 1000;
            if (!first || from < first) {
              first = from;
            }
            if (!last || to > last) {
              last = to;
            }

            tasks.push({
              id: `${plantId}-${stage.type}`,
              entityType: 'asset',
              entityId: plantId,
              entityBundle: 'plant',
              stage: stage.type,
              label: ' ',
              resourceId: assetRowId,
              from: from,
              to: to,
              enableDragging: false,
              classes: [`stage--${stage.type}`],
            });
          });

          for (let logId in plant.logs) {
            let log = plant.logs[logId];

            // Update start/end.
            let from = log.timestamp * 1000;
            let to = from + (86400 * 1000);
            if (!first || from < first) {
              first = from;
            }
            if (!last || to > last) {
              last = to;
            }

            tasks.push({
              id: `${plantId}-log-${log.id}`,
              entityType: 'log',
              entityId: log.id,
              entityBundle: log.type,
              label: ' ',
              resourceId: assetRowId,
              from: from,
              to: to,
              enableDragging: false,
              classes: [
                'log',
                `log--${log.type}`,
                `log-status--${log.status}`
              ],
            });
          };
        }

        // Finally, create the rows.
        rows.push(row);
      }

      // Update gantt with new data.
      gantt.$set({
        rows: [...rows],
        tasks: [...tasks],
        from: first,
        to: last,
      });
    });

}(drupalSettings));
