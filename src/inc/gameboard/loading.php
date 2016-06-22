<?hh
require_once($_SERVER['DOCUMENT_ROOT'] . '/../vendor/autoload.php');

echo \HH\Asio\join(genRenderLoading());

async function genRenderLoading(): Awaitable<string> {
  await tr_start();
  return '
<!-- gameboard loading -->
<div class="gameboard-loading fb-container container--small">
    <h2>'.tr('Initiating').'</h2>
    <h4>'.tr('run : > boot_sequence').'</h4>

    <div class="game-progress fb-progress-bar">
        <span class="label label--left">['.tr('Extracting').']</span>
        <div class="indicator game-progress-indicator">
            <span class="indicator-cell"></span>
            <span class="indicator-cell"></span>
            <span class="indicator-cell"></span>
            <span class="indicator-cell"></span>
            <span class="indicator-cell"></span>
            <span class="indicator-cell"></span>
            <span class="indicator-cell"></span>
            <span class="indicator-cell"></span>
            <span class="indicator-cell"></span>
            <span class="indicator-cell"></span>
        </div>
    </div>

    <div class="boot-sequence">_edata = .; /* End of data section */<br>. = ALIGN(8192);/* init_task */ <br>.data.init_task : { *(.data.init_task) } <br><br>. = ALIGN(4096);/* Init code and data */<br>__init_begin = .;<br>.text.init : { *(.text.init) }<br>.data.init : { *(.data.init) }<br>. = ALIGN(16);<br>__setup_start = .;<br>.setup.init : { *(.setup.init) }<br>__setup_end = .;<br>__initcall_start = .;<br>.initcall.init : { *(.initcall.init) }<br>__initcall_end = .;<br>. = ALIGN(4096);<br>__init_end = .;<br><br>. = ALIGN(4096); <br>.data.page_aligned : { *(.data.idt) } <br><br>. = ALIGN(32); <br>.data.cacheline_aligned : { *(.data.cacheline_aligned) }</div>
</div><!-- .gameboard-loading -->';
}
