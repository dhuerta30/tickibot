<style>
    #oopss {
        background: #fff;
        position: fixed;
        left: 0px;
        top: 0;
        width: 100%;
        height: 100%;
        line-height: 1.5em;
        z-index: 9999;
    }

    #oopss #error-text {
        font-size: 40px;
        display: flex;
        flex-direction: column;
        align-items: center;
        font-family: 'Shabnam', Tahoma, sans-serif;
        color: #000;
        direction: rtl;
    }

    #oopss #error-text img {
        margin: 85px auto 58px;
        height: 200px;
    }

    #oopss #error-text span {
        position: relative;
        font-size: 3.3em;
        font-weight: 900;
        margin-bottom: 0px;
    }

    #oopss #error-text p.p-a {
        font-size: 19px;
        margin: 30px 0 15px 0;
    }

    #oopss #error-text p.p-b {
        font-size: 15px;
    }

    #oopss #error-text .back {
        background: #fff;
        color: #000;
        font-size: 30px;
        text-decoration: none;
        margin: 2em auto 0;
        padding: .7em 2em;
        border-radius: 500px;
        box-shadow: 0 20px 70px 4px rgba(0, 0, 0, 0.1), inset 7px 33px 0 0px #fff300;
        font-weight: 900;
        transition: all 300ms ease;
    }

    #oopss #error-text .back:hover {
        -webkit-transform: translateY(-13px);
        transform: translateY(-13px);
        box-shadow: 0 35px 90px 4px rgba(0, 0, 0, 0.3), inset 0px 0 0 3px #000;
    }

    @font-face {
        font-family: Shabnam;
        src: url("https://cdn.rawgit.com/ahmedhosna95/upload/ba6564f8/fonts/Shabnam/Shabnam-Bold.eot");
        src: url("https://cdn.rawgit.com/ahmedhosna95/upload/ba6564f8/fonts/Shabnam/Shabnam-Bold.eot?#iefix") format("embedded-opentype"), url("https://cdn.rawgit.com/ahmedhosna95/upload/ba6564f8/fonts/Shabnam/Shabnam-Bold.woff") format("woff"), url("https://cdn.rawgit.com/ahmedhosna95/upload/ba6564f8/fonts/Shabnam/Shabnam-Bold.woff2") format("woff2"), url("https://cdn.rawgit.com/ahmedhosna95/upload/ba6564f8/fonts/Shabnam/Shabnam-Bold.ttf") format("truetype");
        font-weight: bold;
    }

    @font-face {
        font-family: Shabnam;
        src: url("https://cdn.rawgit.com/ahmedhosna95/upload/ba6564f8/fonts/Shabnam/Shabnam.eot");
        src: url("https://cdn.rawgit.com/ahmedhosna95/upload/ba6564f8/fonts/Shabnam/Shabnam.eot?#iefix") format("embedded-opentype"), url("https://cdn.rawgit.com/ahmedhosna95/upload/ba6564f8/fonts/Shabnam/Shabnam.woff") format("woff"), url("https://cdn.rawgit.com/ahmedhosna95/upload/ba6564f8/fonts/Shabnam/Shabnam.woff2") format("woff2"), url("https://cdn.rawgit.com/ahmedhosna95/upload/ba6564f8/fonts/Shabnam/Shabnam.ttf") format("truetype");
        font-weight: normal;
    }
</style>
<div id='oopss'>
    <div id='error-text'>
        <img src="<?=$_ENV["BASE_URL"]?>theme/img/logo.png" alt="404">
        <span>404</span>
        <p class="p-a">
            La página que estabas buscando no se pudo encontrar</p>
    </div>
</div>