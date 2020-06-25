(function ($) {
  Drupal.behaviors.farm_crop_plan_log_status = {
    attach: function (context, settings) {

      // Select date elements, update on change.
      $("input[name*='[date]']").change(function() {

        // Get the name to match later on - should be:
        // timeline[plantings][id][log_type][date]
        // new[log_type][date]
        const matches = this.name.match(/^(new|timeline\[plantings\]\[\d+\])(\[.*\]\[date\])/);

        // Bail if no matches.
        if (!matches || matches[0] === 'undefined') {
          return;
        }
        const dateName = matches[0];

        // Get date value, bail if empty.
        const dateval = $(`input[name='${dateName}']`).val();
        if (!dateval) {
          return;
        }

        // Calculate date.
        const date = Date.parse(dateval);

        // Get timestamp of today midnight.
        const today = new Date();
        today.setUTCHours(0,0,0,0);

        // Calculate the log status from current date.
        let completed = false;
        if (date < today.getTime()) {
          completed = true;
        }

        // Update relative checkbox.
        const doneName = dateName.slice(0, -12) + '[done]';
        $(`input[name='${doneName}']`).prop("checked", completed);
      });
    }
  };
}(jQuery));
