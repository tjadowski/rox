<div class="row">
    <div class="col-12">
<?php
$showOnlyOnlineActivities = true;
if (count($this->activities) == 0) {
?>
<p><?php 
if ($this->publicOnly) {
    echo $words->get('ActivitiesNoPublicUpcoming');
} else {
    echo $words->get('ActivitiesNoUpcoming');
}

?></p>
    <?php
} else {
?>
<?php
        require_once('activitieslist.php');
}
?>
    </div>
</div>