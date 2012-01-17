<ul>
  <li><a href="<?php echo FW::baseUrl(); ?>/">Home</a></li>
  <li><a href="<?php echo FW::baseUrl(); ?>/index.php/contact">Contact</a></li>
  <li><a href="<?php echo FW::baseUrl(); ?>/index.php/static/about">About</a></li>
  <?php if (FW::get('auth')) : ?>
    <li><a href="<?php echo FW::baseUrl(); ?>/index.php/member/signout">Sign out</a></li>
  <?php else : ?>
    <li><a href="<?php echo FW::baseUrl(); ?>/index.php/member">Member Area</a></li>
  <?php endif; ?>
</ul>