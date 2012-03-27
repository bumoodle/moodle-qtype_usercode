<link rel="stylesheet" href="/scripts/codemirror/codemirror.css" />
<link rel="stylesheet" href="/scripts/codemirror/default.css" />
<script src="/scripts/codemirror/codemirror.js"></script>
<script src="/scripts/codemirror/hcs08.js"></script>


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

        try
        {

            //create a new UCS session
            $ucs = new UserCodeSession();

            //load the user code
            $ucs->load_code($_POST['code']);

            //set the current program's cap to 1000 cycles
            $ucs->limit_runtime(1000);

            //run the user code
            $ucs->run();

            //and print the code's state
            print_r($ucs->get_state());

            //close the UCS session
            $ucs->close();
        }
        catch(UserCodeException $e)
        {
            //print the exception's message
            echo $e->getMessage();
        }
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
