<table class="artify-table artify-table-view table table-bordered table-striped table-condensed">            
    <tbody>
        <?php
        foreach ($data as $row) {
                ?>
                <tr>
                    <td>
                        <?php if (isset($columns[$row["fieldName"]]))
                            echo $columns[$row["fieldName"]]["colname"];
                        else
                            echo $row["fieldName"];
                        ?>
                    </td>
                    <td>
                <?php if(is_array($row)){ 
                        echo $row["addOnBefore"];
                        echo $row["element"]; 
                        echo $row["addOnAfter"];
                        }
                      else 
                          echo $row; ?>
                    </td>
                </tr>
                <?php
        }
        if (count($leftJoinData)) {
            $key = array_keys($leftJoinData);
            $loop = 0;
            foreach ($leftJoinData as $data) {
                foreach ($data as $rows) {
                    if ($loop === 0) {
                        $leftCols = array_keys($rows);
                        ?>
                        <tr class="artify_view_left_join">
                                <?php
                                foreach ($leftCols as $col) {
                                    ?>
                                <td>
                                <?php if (isset($columns[$col]))
                                    echo $columns[$col]["colname"];
                                else
                                    echo $col;
                                ?>
                                </td>
                            <?php }
                            ?>
                        </tr> 
                            <?php } $loop++;
                            ?>
                    <tr>
                        <?php foreach ($rows as $row) {
                            ?>  
                            <td>
                        <?php echo $row; ?>
                            </td>
                    <?php }
                    ?>
                    </tr>
                        <?php
                    }
                }
            }
            ?>
    </tbody>
    <tfoot>
        <tr>
            <td>                       
                <div class="artify-action-buttons artify-print-hide">
                    <?php if (isset($settings["viewPrintButton"]) && $settings["viewPrintButton"] === true) { ?>
                        <button data-action="print" class="btn btn-info artify-form-control artify-button artify-view-print" type="button"><?php echo $lang["print"] ?></button>
                    <?php } ?>
                    <?php if (isset($settings["viewDelButton"]) && $settings["viewDelButton"] === true) { ?>
                        <button class="btn btn-info artify-form-control artify-button artify-view-delete" data-id="<?php echo $id;?>" data-action="delete" type="button"><?php echo $lang["delete"] ?></button>
                     <?php } ?>
                </div>                       
            </td>
            <td class="text-right">                        
                <div class="artify-action-buttons artify-button-delete">
                    <?php if (isset($settings["viewEditButton"]) && $settings["viewEditButton"] === true) { ?>
                        <button class="btn btn-info artify-form-control artify-button artify-view-edit" data-id="<?php echo $id;?>" data-action="edit" type="button"><?php echo $lang["edit"] ?></button>
                    <?php } ?>
                    <?php if (isset($settings["viewBackButton"]) && $settings["viewBackButton"] === true) { ?>
                        <button data-action="back" data-dismiss="modal" class="btn btn-info artify-form-control artify-button artify-back" type="button"><?php echo $lang["back"] ?></button>
                    <?php } ?>
                    <?php if (isset($settings["closeButton"]) && $settings["closeButton"] === true) { ?>
                        <button data-action="close" data-dismiss="modal" class="btn btn-info artify-form-control artify-button artify-close" type="button"><?php echo $lang["close"] ?></button>
                    <?php } ?>
                </div>                         
            </td>
        </tr>
    </tfoot>
</table>
    