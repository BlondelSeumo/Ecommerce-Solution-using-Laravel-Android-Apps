<!-- //banner one -->
<div class="banner-one">
      <div class="container">
        <div class="group-banners" id="app">
          <?php  $data = json_encode($result); ?>
          <ad-banner1-component :data="{{$data}}"></ad-banner1-component>
        </div>
      </div>
</div>
