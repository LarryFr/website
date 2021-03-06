<div id="accordion">
<?php
foreach ($viewModel as $education)
{
    date_default_timezone_set('Europe/Paris');
    // Récupération du mois + année de date_start et date_end (si non vide)
    $datestart = new DateTime($education['date_start']);
    $dateend = new DateTime();
    $bCurrent = true;
    if ($education['date_end'])
    {
        $dateend = new DateTime($education['date_end']);
        $bCurrent = false;
    }
    $title = $datestart->format('m-Y').' - ';
    $title .= ($bCurrent ? 'XX-XXXX' : $dateend->format('m-Y'));
    $title .= ' : '.$education['institution'];
    ?>
    <h2><?= urldecode($title); ?></h2>
    <div>
        <h4><?= urldecode($education['title']); ?></h4>
        <p><?= urldecode($education['description']); ?></p>
        <a href="<?= urldecode($education['link_diploma']); ?>" target="_blank">
            <img height="100" width="150" src="<?= urldecode($education['link_diploma']); ?>" alt="<?= urldecode($title); ?>"/>
        </a>
    </div >
    <?php
}
?>
</div>
<script>
    $( "#accordion" ).accordion({collapsible: true});
    $( "#accordion div" ).css("height","auto");
</script>
