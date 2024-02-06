(async function (drupalSettings) {

  // Instantiate gantt chart.
  const target = document.getElementById('timeline');
  var gantt = new SvelteGantt({
    // target a DOM element
    target,
    // svelte-gantt options
    props: {
      target: target,
      from: Date.now()-86400,
      to: Date.now(),
      fitWidth: true,
      columnUnit: 'day',
      columnOffset: 7,
      rowHeight: 25,
      rowPadding: 4,
      reflectOnParentRows: false,
      tableHeaders: [{ title: 'By Plant Type', property: 'label', type: 'tree' }],
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

    if (task?.meta?.entity_type == 'log') {
      div.innerHTML = `
            <div class="sg-popup-title">${task.label}</div>
            <div>${task.meta.entity_bundle} ${task.meta.entity_type}: ${task.entity_id}</div>
            <div>Timestamp: ${new Date(task.from).toLocaleDateString()}</div>
        `;
    }

    if (task?.meta?.stage) {
      div.innerHTML = `
            <div class="sg-popup-title">${task.label}</div>
            <div>Stage: ${task.meta.stage}</div>
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
    if (task.model?.editUrl) {
      var ajaxSettings = {
        url: task.model.editUrl,
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

  // Helper function to map row properties.
  const mapRow = function(row) {
    return {
      id: row.id,
      label: row.label,
      headerHtml: row.link,
      expanded: row.expanded,
    };
  }

  // Helper function to map task properties.
  const mapTask = function(task) {
    return {
      id: task.id,
      resourceId: task.resource_id,
      from: new Date(task.start),
      to: new Date(task.end),
      label: ' ', //task.label,
      editUrl: task.edit_url,
      enableDragging: task.enable_dragging,
      meta: task?.meta,
      classes: task.classes,
    };
  }

  // Helper function to process a row.
  // Collect tasks and child rows and child tasks.
  const processRow = function(row) {

    // Map to a row object.
    let mappedRow = mapRow(row);

    // Collect all tasks for the row.
    let tasks = row?.tasks?.map(mapTask) ?? [];

    // Process children rows.
    // Only create the children array if there are child rows.
    let processedChildren = row?.children?.map(processRow) ?? [];
    if (processedChildren.length) {
      mappedRow.children = [];
      processedChildren.forEach((child) => {
        mappedRow.children.push(child.row);
        tasks.push(...child.tasks)
      });
    }

    return {row: mappedRow, tasks};
  }

  // Build a url to the plan timeline API.
  const planId = target.dataset.planId;
  const url = new URL(`plan/${planId}/timeline/plant-type`, window.location.origin + drupalSettings.path.baseUrl);
  const response = fetch(url)
    .then(res => res.json())
    .then(data => {

      // Build new list of rows/tasks.
      const rows = [];
      const tasks = [];

      // Process each row.
      for (let i in data.rows) {
         let row = processRow(data.rows[i]);
         rows.push(row.row);
         tasks.push(...row.tasks)
      }

      // Keep track of first/last timestamps.
      let first = null;
      let last = null;

      // Update the first and last from each task.
      for (let i in tasks) {
        if (!first || tasks[i].from < first) {
          first = tasks[i].from;
        }
        if (!last || tasks[i].to > last) {
          last = tasks[i].to;
        }
      }

      // Define the start, end, and now timestamps.
      // Start and end are padded by a week.
      const start = first.getTime() - (86400 * 7 * 1000);
      const end = last.getTime() + (86400 * 7 * 1000);
      const now = new Date().getTime();

      // If the start of this timeline is in the past, build a time range to
      // represent "the past".
      let timeRanges = [];
      if (start < now) {
        timeRanges.push({
          id: 'past',
          from: start,
          to: now,
          label: 'Past',
          resizable: false,
        });
      }

      // Update gantt.
      gantt.$set({
        rows: rows,
        tasks: tasks,
        timeRanges: timeRanges,
        from: start,
        to: end,
      });
    });

}(drupalSettings));
