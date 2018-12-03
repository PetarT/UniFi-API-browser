<?php

/* layouts/footer.twig */
class __TwigTemplate_f8ea68645dc1601749b04cbcdbf7ed5b2ec9430d1386c85ee09607582b5e8049 extends Twig_Template
{
    private $source;

    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = array(
        );
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        // line 1
        echo "<div class=\"container\">
    <p class=\"float-right\">
        <a href=\"#\">Nazad na početak stranice</a>
    </p>
    <p>WingWiFi aplikacija za upravljanje WiFi vaučerima! Razvio i održava Wing IT tim.</p>
</div>
";
    }

    public function getTemplateName()
    {
        return "layouts/footer.twig";
    }

    public function getDebugInfo()
    {
        return array (  23 => 1,);
    }

    public function getSourceContext()
    {
        return new Twig_Source("", "layouts/footer.twig", "F:\\wamp\\www\\wingwifi\\views\\layouts\\footer.twig");
    }
}
