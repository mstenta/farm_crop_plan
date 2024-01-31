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
              type: 'task',
              id: `${plantId}-${stage.type}`,
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
              type: 'task',
              id: `${plantId}-log-${log.id}`,
              label: ' ',
              resourceId: assetRowId,
              from: from,
              to: to,
              enableDragging: false,
              classes: ['log', `log--${log.type}`],
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
