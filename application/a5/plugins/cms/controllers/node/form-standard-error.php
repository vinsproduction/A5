<?php
$form_errors = $form->get_all_errors();
$first_field_name = reset(array_keys($form_errors));
layout(CMS_PREFIX . "/html-empty");
return render_view("form_errors");