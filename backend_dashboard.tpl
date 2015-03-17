<section id="dashboard_sharedcount" class="odd equal_heights quick_list">
<h3>SharedCount <a href="index.php?/plugin/sharedcount_refresh" id="dashboard_sharedcount_reload"><span class="icon-clock" style="float:right;" title="Force Reload"></span><span class="visuallyhidden">Reload</span></a></h3>
<script>
    $('#dashboard_sharedcount_reload').on('click', function(e) {
        var reloadUrl = $(this).attr('href');
        $.ajax({
            url: reloadUrl,
            success: function (data) {
//                var obj = $.parseJSON(data);
//                $.each(obj, function (index, sharedcount) {
//                    console.log(sharedcount)
//                });
                location.reload(true)
            }
        });
        e.preventDefault();
    });
</script>
<ol class="plainList">
    {foreach from=$sharedcount_entries item="sharedcount_entry"}
    <li class="clearfix" >
        <a href="?serendipity[action]=admin&amp;serendipity[adminModule]=entries&amp;serendipity[adminAction]=edit&amp;serendipity[id]={$sharedcount_entry.id}" title="#{$sharedcount_entry.id}: {$sharedcount_entry.title|escape}">{$sharedcount_entry.title}</a ><br/>
        <small id="sharedcount_entry_{$sharedcount_entry.id}">{$sharedcount_entry.sharedcount}</small>
    </li >
    {/foreach}
</ol>
</section>
