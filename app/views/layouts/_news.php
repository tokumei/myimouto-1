<?php if (!CONFIG()->enable_news_ticker) return; ?>
<div id="news-ticker" style="display: none">
  <ul>
    <li>MyImouto is now on <a href="https://github.com/VeryCrushed/myimouto">GitHub</a>.</li>
  </ul>

  <a href="#" id="close-news-ticker-link"><?= $this->t('.close') ?></a>
</div>
