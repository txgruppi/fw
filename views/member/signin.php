<?php FW::set('title','Login - '); ?>
<h1>FW Sample App - Member Area</h1>

<form method="post">
  <?php if ($error) : ?>
  <div class="error">Login error.</div>
  <?php endif; ?>
  <label for="name">Login</label><br/>
  <input type="text" name="login" id="login" /><small> (Login: sample)</small><br/>
  <br/>
  <label for="message">Password</label><br/>
  <input type="password" name="password" id="password" /><small> (Password: sample)</small><br/>
  <br/>
  <input type="submit" />
</form>