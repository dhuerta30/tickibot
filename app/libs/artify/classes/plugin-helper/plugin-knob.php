<script type="text/javascript">
    jQuery(document).on("artify_on_load artify_after_submission artify_after_ajax_action", function (event, container) {
        jQuery("<?php echo $elementName; ?>").knob({
                <?php
                if (isset($params))
                    echo implode(', ', array_map(
                                    function ($v, $k) {
                                return $k . ':' . $v;
                            }, $params, array_keys($params)
                    ));
                ?>
        });
    });
</script>    