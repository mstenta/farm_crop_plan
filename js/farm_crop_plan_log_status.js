(function ($) {
  Drupal.behaviors.farm_crop_plan_log_status = {
    attach: function (context, settings) {

      // Select date elements, update on change.
      $("select[name*='[date]']").change(function() {

        // Get the name prefix to match later on - should be:
        // plantings[id][log_type][date]
        // new[log_type][date]
        const matches = this.name.match(/^(new|plantings\[\d+\])(\[.*\]\[date\])/);
        // Bail if no matches.
        if (matches[0] === 'undefined') {
          return;
        }
        const prefix = matches[0];

        // Get date parts.
        const year = $(`select[name='${prefix}[year]']`).val();
        const month = $(`select[name='${prefix}[month]']`).val();
        const day = $(`select[name='${prefix}[day]']`).val();

        // Bail if incomplete date.
        if (!year || !month || !day) {
          return;
        }

        // Calculate date.
        const date = Date.parse(`${year}/${month}/${day}`);

        // Get timestamp of today midnight.
        const today = new Date();
        today.setHours(24,0,0,0);

        // Calculate the log status from current date.
        let completed = false;
        if (date < today) {
          completed = true;
        }

        // Update relative checkbox.
        const doneName = prefix.slice(0, -6) + '[done]';
        $(`input[name='${doneName}']`).prop("checked", completed);
      });
    }
  };
}(jQuery));
