<?php include '../../assets/includes/header.php'; ?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de devoluciones y reembolsos</title>
    <meta name="description" content="Consulta nuestra política de devoluciones y reembolsos para conocer los términos y condiciones sobre cómo puedes devolver productos y solicitar un reembolso de manera sencilla y segura.">
    <link rel="stylesheet" href="../../../assets/css/styles.css">
</head>

<main>
    <h1>Política de devoluciones y reembolsos</h1>
    
    <h2>1. Derecho de Desistimiento</h2>
    <p>En cumplimiento con la Ley General para la Defensa de los Consumidores y Usuarios (Real Decreto Legislativo 1/2007), los consumidores tienen derecho a desistir de su compra en un plazo de 14 días naturales sin necesidad de justificación.</p>
    
    <h2>2. Plazo de Devolución</h2>
    <p>El plazo de desistimiento expirará a los 14 días naturales desde el día en que el consumidor o un tercero indicado por el consumidor, distinto del transportista, adquirió la posesión material de los productos.</p>
    
    <h2>3. Procedimiento de Devolución</h2>
    <p>Para ejercer el derecho de desistimiento, el consumidor deberá notificar su decisión de desistir del contrato a través de una declaración inequívoca a la dirección de contacto proporcionada en el Marketplace.</p>
    
    <h2>4. Procesamiento de Reembolsos</h2>
    <p><strong>4.1.</strong> Una vez que recibamos y verifiquemos su devolución, le notificaremos sobre la aprobación o rechazo de su reembolso.</p>
    <p><strong>4.2.</strong> Si se aprueba, su reembolso será procesado y se aplicará automáticamente a su método de pago original dentro de 10 días hábiles.</p>
    
    <h2>5. Condiciones de la Devolución</h2>
    <p>Los productos deberán ser devueltos en su estado original y con el embalaje original intacto.</p>
    <ul>
        <li>Los productos de salud no serán devueltos.</li>
        <li>Los productos personalizados no serán devueltos.</li>
        <li>Bisutería y accesorios, no serán devueltos. (Exceptuando bolsos, bufandas y mantas)</li>
        <li>Ropa interior y bodys no serán devueltos.</li>
        <li>Decoración y productos de fiesta no serán devueltos.</li>
        <li>Productos para ingerir, como medicamentos, alimentos, bebidas, vitaminas y suplementos no serán devueltos.</li>
    </ul>
    
    <h2>6. Coste de la Devolución</h2>
    <p>El consumidor asumirá el coste directo de devolución de los productos, salvo que el vendedor haya acordado asumir dicho coste.</p>
    
    <h2>7. Artículos Defectuosos o Dañados</h2>
    <p>Si recibe un artículo defectuoso o dañado, por favor contáctenos inmediatamente para que podamos evaluar el problema y corregirlo.</p>
    
    <h2>8. Cambios</h2>
    <p>La forma más rápida de asegurar que obtenga lo que desea es devolver el artículo que tiene y, una vez que se acepte la devolución, realizar una compra por separado del nuevo artículo.</p>
    
    <h2>9. Contacto</h2>
    <p>Para cualquier duda o consulta sobre nuestra política de devoluciones, puede ponerse en contacto con nosotros a través de nuestra página de soporte.</p>
</main>

<style>
    main {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        font-family: "Poppins";
        line-height: 1.6;
    }

    h1 {
        font-size: 2em;
        color: #333;
        margin-bottom: 20px;
        text-align: center;
        font-family: "Poppins";
    }

    h2 {
        font-size: 1.5em;
        color: #000;
        margin-top: 30px;
        margin-bottom: 15px;
        font-family: "Poppins";
    }

    p {
        font-size: 1em;
        color: #666;
        margin-bottom: 15px;
        font-family: "Poppins";
    }

    ul {
        list-style-type: disc;
        padding-left: 20px;
        margin-bottom: 20px;
        font-family: "Poppins";
    }

    li {
        font-size: 1em;
        color: #666;
        margin-bottom: 10px;
        font-family: "Poppins";
    }

    a {
        color: #007BFF;
        text-decoration: none;
        font-family: "Poppins";
    }

    a:hover {
        text-decoration: underline;
    }

    strong {
        color: #333;
        font-family: "Poppins";
    }

    @media (max-width: 600px) {
        main {
            padding: 10px;
        }

        h1 {
            font-size: 1.5em;
        }

        h2 {
            font-size: 1.2em;
        }

        p, li {
            font-size: 0.9em;
        }
    }
</style>

<?php include '../../assets/includes/footer.php'; ?>
<script src="../../assets/js/header.js"></script>
<script>
    document.querySelectorAll('.faq-item h3').forEach(i => i.addEventListener('click', () => i.parentElement.classList.toggle('active')));
    document.getElementById('search').addEventListener('input', function() {
        let f = this.value.toLowerCase();
        document.querySelectorAll('.faq-item').forEach(i => i.style.display = i.querySelector('h3').innerText.toLowerCase().includes(f) ? "block" : "none");
    });
</script>