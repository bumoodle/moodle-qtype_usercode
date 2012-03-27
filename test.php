<link rel="stylesheet" href="/m/scripts/codemirror/codemirror.css" />
<link rel="stylesheet" href="/m/scripts/codemirror/default.css" />
<script src="/m/scripts/codemirror/codemirror.js"></script>
<script src="/m/scripts/codemirror/hcs08.js"></script>


<form action="" method="POST">
    <?php
        if(!isset($_POST['code']))
            $_POST['code'] = "\t";
    ?>
    <p>
        <textarea name="code" id="code" rows="10" cols="100"><?php echo htmlentities($_POST['code']); ?></textarea>
    </p>
    <input type="submit" value="Simulate" />
</form>
<pre>
    <?php

    if($_POST['code'] !== "\t")
    {

        require_once 'UserCodeInterop.class.php';

        //create a new UCS session
        $ucs = new UserCodeSession();

        //load the user code
        $ucs->load_code($_POST['code']);

        //run the user code
        $ucs->run();

        //and print the code's state
        print_r($ucs->get_state());

        //close the UCS session
        $ucs->close();
    }

    ?>
</pre>

    <script>
      var editor = CodeMirror.fromTextArea(document.getElementById("code"), {
        lineNumbers: true,
        matchBrackets: true,
        theme: 'elegant',       
        mode: "text/asm-hcs08"
      });


    </script>
