<?php

/* base.twig */
class __TwigTemplate_c0ceeddaefb1050bd3327e4bac1826c56dd4df108f6a475647e2c6295c92cfed extends Twig_Template
{
    private $source;

    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = array(
            'header' => array($this, 'block_header'),
            'main' => array($this, 'block_main'),
            'scripts' => array($this, 'block_scripts'),
        );
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        // line 1
        echo "<!doctype html>
<html lang=\"sr\">
    <head>
        ";
        // line 4
        $this->displayBlock('header', $context, $blocks);
        // line 12
        echo "    </head>
    <body>
        <main role=\"main\">
            <div class=\"container\">
                <div class=\"row\">
                ";
        // line 17
        $this->displayBlock('main', $context, $blocks);
        // line 19
        echo "                </div>
            </div>
        </main>

        <footer class=\"footer text-muted\">
            ";
        // line 24
        $this->loadTemplate("layouts/footer.twig", "base.twig", 24)->display($context);
        // line 25
        echo "        </footer>

        ";
        // line 27
        $this->displayBlock('scripts', $context, $blocks);
        // line 32
        echo "    </body>
</html>
";
    }

    // line 4
    public function block_header($context, array $blocks = array())
    {
        // line 5
        echo "        <meta charset=\"utf-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1, shrink-to-fit=no\">
        <link rel=\"stylesheet\" href=\"/assets/css/bootstrap.min.css\" />
        <link rel=\"stylesheet\" href=\"/assets/css/fontawesome.min.css\" />
        <link rel=\"stylesheet\" href=\"/assets/css/custom.css\" />
        <title>WingWiFi Vaučeri</title>
        ";
    }

    // line 17
    public function block_main($context, array $blocks = array())
    {
        // line 18
        echo "                ";
    }

    // line 27
    public function block_scripts($context, array $blocks = array())
    {
        // line 28
        echo "        <script src=\"/assets/js/jquery.min.js\"></script>
        <script src=\"/assets/js/bootstrap.bundle.min.js\"></script>
        <script src=\"/assets/js/custom.js\"></script>
        ";
    }

    public function getTemplateName()
    {
        return "base.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  86 => 28,  83 => 27,  79 => 18,  76 => 17,  66 => 5,  63 => 4,  57 => 32,  55 => 27,  51 => 25,  49 => 24,  42 => 19,  40 => 17,  33 => 12,  31 => 4,  26 => 1,);
    }

    public function getSourceContext()
    {
        return new Twig_Source("", "base.twig", "F:\\wamp\\www\\wingwifi\\views\\base.twig");
    }
}
