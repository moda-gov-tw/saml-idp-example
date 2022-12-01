
<form action="/dologin" method="post">
<input type="text" name="username" value="{{ $request->username }}"><br>
<input type="hidden" name="SAMLRequest" value="{{ $request->SAMLRequest }}">
<input type="hidden" name="RelayState" value="{{ $request->RelayState }}">
<button type="submit">登入</button>
</form>