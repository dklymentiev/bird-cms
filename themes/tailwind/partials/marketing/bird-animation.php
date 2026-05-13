<?php
/**
 * Animated Bird CMS hummingbird (extracted from bird-cms-brand.html).
 *
 * Self-contained: ships its own scoped CSS + SVG, no external deps. Drop into
 * any view to decorate. Animation is one-shot (about 4 seconds end-to-end);
 * after settling, the static logo remains in place.
 *
 * Render via: $theme->partial('marketing/bird-animation');
 */
?>
<style>
.bird-hero-mark {
    display: block;
    width: 240px; height: 240px;
    transform-origin: center;
    animation: bird-hero-sway 2.5s 1.2s cubic-bezier(.4, 0, .4, 1) forwards;
    pointer-events: none;
}
.bird-hero-mark svg { width: 100%; height: 100%; overflow: visible; }
@keyframes bird-hero-sway {
    0%   { transform: translate(0,0) rotate(0deg); }
    10%  { transform: translate(-7px,-8px) rotate(-3deg); }
    22%  { transform: translate( 8px,-5px) rotate( 3deg); }
    38%  { transform: translate(-6px,-6px) rotate(-2.2deg); }
    54%  { transform: translate( 6px,-3px) rotate( 2.2deg); }
    70%  { transform: translate(-3px,-3px) rotate(-1deg); }
    84%  { transform: translate( 2px,-1px) rotate( 1deg); }
    100% { transform: translate(0,0) rotate(0deg); }
}
.bird-hero-mark .dot {
    fill: #ff2d8a; stroke: white; stroke-width: 1.5; opacity: 0;
    animation:
        bh-dot-in  0.25s var(--d) ease-out forwards,
        bh-dot-out 0.4s 0.95s ease-in forwards;
}
@keyframes bh-dot-in  { 0% { opacity:0; r:0; } 60% { r:6; } 100% { opacity:1; r:5; } }
@keyframes bh-dot-out { to { opacity:0; r:0; } }
.bird-hero-mark .edge {
    stroke: #f8f6f3; stroke-width: 1.5; fill: none; stroke-linecap: round;
    animation:
        bh-edge-draw 0.4s var(--d) cubic-bezier(.4,0,.2,1) forwards,
        bh-edge-out  0.4s 1.0s ease-in forwards;
}
@keyframes bh-edge-draw { 0% { stroke-dashoffset: var(--len); } 100% { stroke-dashoffset: 0; } }
@keyframes bh-edge-out  { to { opacity: 0; } }
.bird-hero-mark .face { opacity: 0; animation: bh-face-in 0.4s 0.95s ease-out forwards; }
@keyframes bh-face-in { from { opacity: 0; } to { opacity: 1; } }
.bird-hero-mark .zone { transform-box: view-box; }
.bird-hero-mark .zone.l-wing {
    transform-origin: 290px 270px;
    animation:
        bh-flap-l    0.06s linear 1.2s 38,
        bh-wing-blur 2.3s  ease-in-out 1.2s 1 forwards;
}
.bird-hero-mark .zone.r-wing {
    transform-origin: 420px 230px;
    animation:
        bh-flap-r    0.06s linear 1.2s 38,
        bh-wing-blur 2.3s  ease-in-out 1.2s 1 forwards;
}
@keyframes bh-flap-l { 0%,100% { transform: rotate(0deg); } 25% { transform: rotate(-9deg); } 75% { transform: rotate( 9deg); } }
@keyframes bh-flap-r { 0%,100% { transform: rotate(0deg); } 25% { transform: rotate( 8deg); } 75% { transform: rotate(-8deg); } }
@keyframes bh-wing-blur {
    0%   { filter: blur(0);    opacity: 1;   }
    15%  { filter: blur(2.5px); opacity: 0.9; }
    80%  { filter: blur(2.5px); opacity: 0.9; }
    100% { filter: blur(0);    opacity: 1;   }
}
@media (prefers-reduced-motion: reduce) {
    .bird-hero-mark { animation: none; }
    .bird-hero-mark .dot, .bird-hero-mark .edge { display: none; }
    .bird-hero-mark .face { opacity: 1; animation: none; }
    .bird-hero-mark .zone { animation: none; }
}
</style>

<div class="bird-hero-mark" aria-hidden="true">
  <svg class="bird-hero-mark-svg" viewBox="0 0 811 811" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet"><g class="lines"><line class="edge" x1="809.0" y1="153.8" x2="775.1" y2="159.7" style="--len:34.4;--d:0.300s" stroke-dasharray="34.4" stroke-dashoffset="34.4"/>
<line class="edge" x1="775.1" y1="159.7" x2="700.0" y2="170.0" style="--len:75.8;--d:0.305s" stroke-dasharray="75.8" stroke-dashoffset="75.8"/>
<line class="edge" x1="775.1" y1="159.7" x2="652.2" y2="162.0" style="--len:122.9;--d:0.309s" stroke-dasharray="122.9" stroke-dashoffset="122.9"/>
<line class="edge" x1="775.1" y1="159.7" x2="630.7" y2="199.0" style="--len:149.7;--d:0.314s" stroke-dasharray="149.7" stroke-dashoffset="149.7"/>
<line class="edge" x1="652.2" y1="178.6" x2="700.0" y2="170.0" style="--len:48.6;--d:0.318s" stroke-dasharray="48.6" stroke-dashoffset="48.6"/>
<line class="edge" x1="547.1" y1="144.6" x2="652.2" y2="162.0" style="--len:106.5;--d:0.323s" stroke-dasharray="106.5" stroke-dashoffset="106.5"/>
<line class="edge" x1="652.2" y1="178.6" x2="630.7" y2="199.0" style="--len:29.6;--d:0.328s" stroke-dasharray="29.6" stroke-dashoffset="29.6"/>
<line class="edge" x1="547.1" y1="144.6" x2="652.2" y2="178.6" style="--len:110.5;--d:0.332s" stroke-dasharray="110.5" stroke-dashoffset="110.5"/>
<line class="edge" x1="652.2" y1="178.6" x2="596.8" y2="189.8" style="--len:56.5;--d:0.337s" stroke-dasharray="56.5" stroke-dashoffset="56.5"/>
<line class="edge" x1="630.7" y1="199.0" x2="596.8" y2="189.8" style="--len:35.1;--d:0.341s" stroke-dasharray="35.1" stroke-dashoffset="35.1"/>
<line class="edge" x1="547.1" y1="144.6" x2="565.5" y2="184.9" style="--len:44.3;--d:0.346s" stroke-dasharray="44.3" stroke-dashoffset="44.3"/>
<line class="edge" x1="580.5" y1="189.5" x2="596.8" y2="189.8" style="--len:16.3;--d:0.351s" stroke-dasharray="16.3" stroke-dashoffset="16.3"/>
<line class="edge" x1="580.5" y1="189.5" x2="565.5" y2="184.9" style="--len:15.7;--d:0.355s" stroke-dasharray="15.7" stroke-dashoffset="15.7"/>
<line class="edge" x1="485.9" y1="179.1" x2="547.1" y2="144.6" style="--len:70.3;--d:0.360s" stroke-dasharray="70.3" stroke-dashoffset="70.3"/>
<line class="edge" x1="582.0" y1="256.3" x2="630.7" y2="199.0" style="--len:75.2;--d:0.364s" stroke-dasharray="75.2" stroke-dashoffset="75.2"/>
<line class="edge" x1="485.9" y1="179.1" x2="565.5" y2="184.9" style="--len:79.8;--d:0.369s" stroke-dasharray="79.8" stroke-dashoffset="79.8"/>
<line class="edge" x1="580.5" y1="189.5" x2="582.0" y2="256.3" style="--len:66.8;--d:0.374s" stroke-dasharray="66.8" stroke-dashoffset="66.8"/>
<line class="edge" x1="478.1" y1="210.6" x2="485.9" y2="179.1" style="--len:32.5;--d:0.378s" stroke-dasharray="32.5" stroke-dashoffset="32.5"/>
<line class="edge" x1="580.5" y1="189.5" x2="504.6" y2="311.1" style="--len:143.3;--d:0.383s" stroke-dasharray="143.3" stroke-dashoffset="143.3"/>
<line class="edge" x1="473.4" y1="243.9" x2="478.1" y2="210.6" style="--len:33.6;--d:0.387s" stroke-dasharray="33.6" stroke-dashoffset="33.6"/>
<line class="edge" x1="564.2" y1="346.5" x2="582.0" y2="256.3" style="--len:91.9;--d:0.392s" stroke-dasharray="91.9" stroke-dashoffset="91.9"/>
<line class="edge" x1="504.6" y1="311.1" x2="582.0" y2="256.3" style="--len:94.8;--d:0.397s" stroke-dasharray="94.8" stroke-dashoffset="94.8"/>
<line class="edge" x1="473.4" y1="243.9" x2="424.0" y2="212.4" style="--len:58.6;--d:0.401s" stroke-dasharray="58.6" stroke-dashoffset="58.6"/>
<line class="edge" x1="473.4" y1="243.9" x2="488.4" y2="261.7" style="--len:23.3;--d:0.406s" stroke-dasharray="23.3" stroke-dashoffset="23.3"/>
<line class="edge" x1="188.4" y1="65.5" x2="424.0" y2="212.4" style="--len:277.6;--d:0.410s" stroke-dasharray="277.6" stroke-dashoffset="277.6"/>
<line class="edge" x1="424.0" y1="212.4" x2="286.8" y2="155.0" style="--len:148.7;--d:0.415s" stroke-dasharray="148.7" stroke-dashoffset="148.7"/>
<line class="edge" x1="488.4" y1="261.7" x2="504.6" y2="311.1" style="--len:52.0;--d:0.420s" stroke-dasharray="52.0" stroke-dashoffset="52.0"/>
<line class="edge" x1="473.4" y1="243.9" x2="408.8" y2="255.5" style="--len:65.6;--d:0.424s" stroke-dasharray="65.6" stroke-dashoffset="65.6"/>
<line class="edge" x1="408.8" y1="255.5" x2="424.0" y2="212.4" style="--len:45.7;--d:0.429s" stroke-dasharray="45.7" stroke-dashoffset="45.7"/>
<line class="edge" x1="195.2" y1="121.3" x2="188.4" y2="65.5" style="--len:56.2;--d:0.433s" stroke-dasharray="56.2" stroke-dashoffset="56.2"/>
<line class="edge" x1="424.0" y1="212.4" x2="269.5" y2="194.3" style="--len:155.6;--d:0.438s" stroke-dasharray="155.6" stroke-dashoffset="155.6"/>
<line class="edge" x1="504.6" y1="311.1" x2="564.2" y2="346.5" style="--len:69.3;--d:0.443s" stroke-dasharray="69.3" stroke-dashoffset="69.3"/>
<line class="edge" x1="473.4" y1="243.9" x2="436.4" y2="308.7" style="--len:74.6;--d:0.447s" stroke-dasharray="74.6" stroke-dashoffset="74.6"/>
<line class="edge" x1="436.4" y1="308.7" x2="488.4" y2="261.7" style="--len:70.1;--d:0.452s" stroke-dasharray="70.1" stroke-dashoffset="70.1"/>
<line class="edge" x1="195.2" y1="121.3" x2="286.8" y2="155.0" style="--len:97.6;--d:0.456s" stroke-dasharray="97.6" stroke-dashoffset="97.6"/>
<line class="edge" x1="444.4" y1="342.7" x2="488.4" y2="261.7" style="--len:92.2;--d:0.461s" stroke-dasharray="92.2" stroke-dashoffset="92.2"/>
<line class="edge" x1="278.7" y1="256.0" x2="424.0" y2="212.4" style="--len:151.7;--d:0.466s" stroke-dasharray="151.7" stroke-dashoffset="151.7"/>
<line class="edge" x1="408.8" y1="255.5" x2="436.4" y2="308.7" style="--len:59.9;--d:0.470s" stroke-dasharray="59.9" stroke-dashoffset="59.9"/>
<line class="edge" x1="341.9" y1="259.9" x2="408.8" y2="255.5" style="--len:67.0;--d:0.475s" stroke-dasharray="67.0" stroke-dashoffset="67.0"/>
<line class="edge" x1="444.4" y1="342.7" x2="504.6" y2="311.1" style="--len:68.0;--d:0.479s" stroke-dasharray="68.0" stroke-dashoffset="68.0"/>
<line class="edge" x1="222.4" y1="186.9" x2="195.2" y2="121.3" style="--len:71.0;--d:0.484s" stroke-dasharray="71.0" stroke-dashoffset="71.0"/>
<line class="edge" x1="526.9" y1="415.4" x2="564.2" y2="346.5" style="--len:78.3;--d:0.489s" stroke-dasharray="78.3" stroke-dashoffset="78.3"/>
<line class="edge" x1="504.6" y1="311.1" x2="526.9" y2="415.4" style="--len:106.7;--d:0.493s" stroke-dasharray="106.7" stroke-dashoffset="106.7"/>
<line class="edge" x1="222.4" y1="186.9" x2="269.5" y2="194.3" style="--len:47.7;--d:0.498s" stroke-dasharray="47.7" stroke-dashoffset="47.7"/>
<line class="edge" x1="436.4" y1="308.7" x2="444.4" y2="342.7" style="--len:34.9;--d:0.502s" stroke-dasharray="34.9" stroke-dashoffset="34.9"/>
<line class="edge" x1="322.9" y1="317.4" x2="408.8" y2="255.5" style="--len:105.9;--d:0.507s" stroke-dasharray="105.9" stroke-dashoffset="105.9"/>
<line class="edge" x1="408.8" y1="255.5" x2="374.7" y2="354.1" style="--len:104.3;--d:0.511s" stroke-dasharray="104.3" stroke-dashoffset="104.3"/>
<line class="edge" x1="278.7" y1="256.0" x2="341.9" y2="259.9" style="--len:63.3;--d:0.516s" stroke-dasharray="63.3" stroke-dashoffset="63.3"/>
<line class="edge" x1="278.7" y1="256.0" x2="222.4" y2="186.9" style="--len:89.1;--d:0.521s" stroke-dasharray="89.1" stroke-dashoffset="89.1"/>
<line class="edge" x1="444.4" y1="342.7" x2="526.9" y2="415.4" style="--len:110.0;--d:0.525s" stroke-dasharray="110.0" stroke-dashoffset="110.0"/>
<line class="edge" x1="374.7" y1="354.1" x2="436.4" y2="308.7" style="--len:76.6;--d:0.530s" stroke-dasharray="76.6" stroke-dashoffset="76.6"/>
<line class="edge" x1="341.9" y1="259.9" x2="322.9" y2="317.4" style="--len:60.6;--d:0.534s" stroke-dasharray="60.6" stroke-dashoffset="60.6"/>
<line class="edge" x1="374.7" y1="354.1" x2="444.4" y2="342.7" style="--len:70.6;--d:0.539s" stroke-dasharray="70.6" stroke-dashoffset="70.6"/>
<line class="edge" x1="341.9" y1="259.9" x2="226.7" y2="307.3" style="--len:124.6;--d:0.544s" stroke-dasharray="124.6" stroke-dashoffset="124.6"/>
<line class="edge" x1="353.2" y1="418.1" x2="444.4" y2="342.7" style="--len:118.3;--d:0.548s" stroke-dasharray="118.3" stroke-dashoffset="118.3"/>
<line class="edge" x1="278.7" y1="256.0" x2="173.1" y2="281.0" style="--len:108.5;--d:0.553s" stroke-dasharray="108.5" stroke-dashoffset="108.5"/>
<line class="edge" x1="526.9" y1="415.4" x2="452.1" y2="475.6" style="--len:96.0;--d:0.557s" stroke-dasharray="96.0" stroke-dashoffset="96.0"/>
<line class="edge" x1="353.2" y1="418.1" x2="374.7" y2="354.1" style="--len:67.5;--d:0.562s" stroke-dasharray="67.5" stroke-dashoffset="67.5"/>
<line class="edge" x1="322.9" y1="317.4" x2="319.7" y2="400.1" style="--len:82.8;--d:0.567s" stroke-dasharray="82.8" stroke-dashoffset="82.8"/>
<line class="edge" x1="374.7" y1="354.1" x2="319.7" y2="400.1" style="--len:71.7;--d:0.571s" stroke-dasharray="71.7" stroke-dashoffset="71.7"/>
<line class="edge" x1="322.9" y1="317.4" x2="235.0" y2="379.5" style="--len:107.6;--d:0.576s" stroke-dasharray="107.6" stroke-dashoffset="107.6"/>
<line class="edge" x1="444.4" y1="342.7" x2="364.0" y2="525.8" style="--len:200.0;--d:0.580s" stroke-dasharray="200.0" stroke-dashoffset="200.0"/>
<line class="edge" x1="5.2" y1="263.1" x2="278.7" y2="256.0" style="--len:273.6;--d:0.585s" stroke-dasharray="273.6" stroke-dashoffset="273.6"/>
<line class="edge" x1="331.5" y1="406.1" x2="353.2" y2="418.1" style="--len:24.8;--d:0.590s" stroke-dasharray="24.8" stroke-dashoffset="24.8"/>
<line class="edge" x1="331.5" y1="406.1" x2="319.7" y2="400.1" style="--len:13.2;--d:0.594s" stroke-dasharray="13.2" stroke-dashoffset="13.2"/>
<line class="edge" x1="134.6" y1="343.3" x2="226.7" y2="307.3" style="--len:98.9;--d:0.599s" stroke-dasharray="98.9" stroke-dashoffset="98.9"/>
<line class="edge" x1="235.0" y1="379.5" x2="319.7" y2="400.1" style="--len:87.2;--d:0.603s" stroke-dasharray="87.2" stroke-dashoffset="87.2"/>
<line class="edge" x1="53.8" y1="301.8" x2="173.1" y2="281.0" style="--len:121.1;--d:0.608s" stroke-dasharray="121.1" stroke-dashoffset="121.1"/>
<line class="edge" x1="392.9" y1="519.6" x2="452.1" y2="475.6" style="--len:73.8;--d:0.613s" stroke-dasharray="73.8" stroke-dashoffset="73.8"/>
<line class="edge" x1="487.4" y1="585.9" x2="452.1" y2="475.6" style="--len:115.8;--d:0.617s" stroke-dasharray="115.8" stroke-dashoffset="115.8"/>
<line class="edge" x1="434.0" y1="552.5" x2="452.1" y2="475.6" style="--len:79.0;--d:0.622s" stroke-dasharray="79.0" stroke-dashoffset="79.0"/>
<line class="edge" x1="353.2" y1="418.1" x2="364.0" y2="525.8" style="--len:108.2;--d:0.626s" stroke-dasharray="108.2" stroke-dashoffset="108.2"/>
<line class="edge" x1="134.6" y1="343.3" x2="235.0" y2="379.5" style="--len:106.7;--d:0.631s" stroke-dasharray="106.7" stroke-dashoffset="106.7"/>
<line class="edge" x1="134.6" y1="343.3" x2="53.8" y2="301.8" style="--len:90.8;--d:0.636s" stroke-dasharray="90.8" stroke-dashoffset="90.8"/>
<line class="edge" x1="434.0" y1="552.5" x2="392.9" y2="519.6" style="--len:52.6;--d:0.640s" stroke-dasharray="52.6" stroke-dashoffset="52.6"/>
<line class="edge" x1="487.4" y1="585.9" x2="434.0" y2="552.5" style="--len:63.0;--d:0.645s" stroke-dasharray="63.0" stroke-dashoffset="63.0"/>
<line class="edge" x1="53.8" y1="301.8" x2="5.2" y2="263.1" style="--len:62.1;--d:0.649s" stroke-dasharray="62.1" stroke-dashoffset="62.1"/>
<line class="edge" x1="364.0" y1="525.8" x2="392.9" y2="519.6" style="--len:29.6;--d:0.654s" stroke-dasharray="29.6" stroke-dashoffset="29.6"/>
<line class="edge" x1="364.0" y1="525.8" x2="378.3" y2="571.4" style="--len:47.8;--d:0.659s" stroke-dasharray="47.8" stroke-dashoffset="47.8"/>
<line class="edge" x1="408.5" y1="645.4" x2="392.9" y2="519.6" style="--len:126.8;--d:0.663s" stroke-dasharray="126.8" stroke-dashoffset="126.8"/>
<line class="edge" x1="408.5" y1="645.4" x2="487.4" y2="585.9" style="--len:98.8;--d:0.668s" stroke-dasharray="98.8" stroke-dashoffset="98.8"/>
<line class="edge" x1="408.5" y1="645.4" x2="434.0" y2="552.5" style="--len:96.3;--d:0.672s" stroke-dasharray="96.3" stroke-dashoffset="96.3"/>
<line class="edge" x1="408.5" y1="645.4" x2="378.3" y2="571.4" style="--len:79.9;--d:0.677s" stroke-dasharray="79.9" stroke-dashoffset="79.9"/>
<line class="edge" x1="368.6" y1="721.8" x2="364.0" y2="525.8" style="--len:196.1;--d:0.682s" stroke-dasharray="196.1" stroke-dashoffset="196.1"/>
<line class="edge" x1="368.6" y1="721.8" x2="378.3" y2="571.4" style="--len:150.7;--d:0.686s" stroke-dasharray="150.7" stroke-dashoffset="150.7"/>
<line class="edge" x1="408.5" y1="645.4" x2="368.6" y2="721.8" style="--len:86.2;--d:0.691s" stroke-dasharray="86.2" stroke-dashoffset="86.2"/>
<line class="edge" x1="368.0" y1="743.0" x2="368.6" y2="721.8" style="--len:21.2;--d:0.695s" stroke-dasharray="21.2" stroke-dashoffset="21.2"/></g><g class="faces"><g class="zone r-wing">
        <path class="face" fill="#f36e2f" d="M 286.8,155.0 L 424.0,212.4 L 269.5,194.3 L 222.4,186.9 L 195.2,121.3 L 286.8,155.0 Z"/>
        <path class="face" fill="#ea5530" d="M 424.0,212.4 L 278.7,256.0 L 222.4,186.9 L 269.5,194.3 L 424.0,212.4 Z"/>
        <path class="face" fill="#f67e32" d="M 424.0,212.4 L 286.8,155.0 L 195.2,121.3 L 188.4,65.5 L 424.0,212.4 Z"/>
        <path class="face" fill="#dd3d31" d="M 278.7,256.0 L 424.0,212.4 L 408.8,255.5 L 341.9,259.9 L 278.7,256.0 Z"/>
        <path class="face" fill="#ba342b" d="M 424.0,212.4 L 473.4,243.9 L 408.8,255.5 L 424.0,212.4 Z"/>
        </g>
<g class="zone tail">
        <path class="face" fill="#429d51" d="M 408.5,645.4 L 434.0,552.5 L 487.4,585.9 L 408.5,645.4 Z"/>
        <path class="face" fill="#13aa97" d="M 378.3,571.4 L 408.5,645.4 L 368.6,721.8 L 378.3,571.4 Z"/>
        <path class="face" fill="#39753f" d="M 487.4,585.9 L 434.0,552.5 L 452.1,475.6 L 487.4,585.9 Z"/>
        <path class="face" fill="#17645f" d="M 434.0,552.5 L 408.5,645.4 L 392.9,519.6 L 434.0,552.5 Z"/>
        <path class="face" fill="#164847" d="M 378.3,571.4 L 364.0,525.8 L 392.9,519.6 L 408.5,645.4 L 378.3,571.4 Z"/>
        <path class="face" fill="#154747" d="M 392.9,519.6 L 452.1,475.6 L 434.0,552.5 L 392.9,519.6 Z"/>
        <path class="face" fill="#12a592" d="M 364.0,525.8 L 378.3,571.4 L 368.6,721.8 L 364.0,525.8 Z"/>
        </g>
<g class="zone body">
        <path class="face" fill="#0b8f5e" d="M 526.9,415.4 L 452.1,475.6 L 392.9,519.6 L 364.0,525.8 L 444.4,342.7 L 526.9,415.4 Z"/>
        <path class="face" fill="#379959" d="M 364.0,525.8 L 353.2,418.1 L 444.4,342.7 L 364.0,525.8 Z"/>
        <path class="face" fill="#098151" d="M 526.9,415.4 L 444.4,342.7 L 504.6,311.1 L 526.9,415.4 Z"/>
        <path class="face" fill="#175b6a" d="M 582.0,256.3 L 564.2,346.5 L 504.6,311.1 L 582.0,256.3 Z"/>
        <path class="face" fill="#1c7886" d="M 504.6,311.1 L 564.2,346.5 L 526.9,415.4 L 504.6,311.1 Z"/>
        <path class="face" fill="#60ba68" d="M 408.8,255.5 L 436.4,308.7 L 374.7,354.1 L 408.8,255.5 Z"/>
        <path class="face" fill="#6ead5a" d="M 353.2,418.1 L 374.7,354.1 L 444.4,342.7 L 353.2,418.1 Z"/>
        <path class="face" fill="#5fb78d" d="M 444.4,342.7 L 488.4,261.7 L 504.6,311.1 L 444.4,342.7 Z"/>
        <path class="face" fill="#7cc577" d="M 374.7,354.1 L 436.4,308.7 L 444.4,342.7 L 374.7,354.1 Z"/>
        <path class="face" fill="#4c9e8c" d="M 488.4,261.7 L 444.4,342.7 L 436.4,308.7 L 488.4,261.7 Z"/>
        </g>
<g class="zone l-wing">
        <path class="face" fill="#deb72e" d="M 341.9,259.9 L 322.9,317.4 L 235.0,379.5 L 134.6,343.3 L 226.7,307.3 L 341.9,259.9 Z"/>
        <path class="face" fill="#f3cc41" d="M 173.1,281.0 L 278.7,256.0 L 341.9,259.9 L 226.7,307.3 L 134.6,343.3 L 53.8,301.8 L 173.1,281.0 Z"/>
        <path class="face" fill="#f6c235" d="M 278.7,256.0 L 173.1,281.0 L 53.8,301.8 L 5.2,263.1 L 278.7,256.0 Z"/>
        <path class="face" fill="#d49c06" d="M 322.9,317.4 L 408.8,255.5 L 374.7,354.1 L 319.7,400.1 L 322.9,317.4 Z"/>
        <path class="face" fill="#bf8d0f" d="M 322.9,317.4 L 319.7,400.1 L 235.0,379.5 L 322.9,317.4 Z"/>
        <path class="face" fill="#f2be2e" d="M 322.9,317.4 L 341.9,259.9 L 408.8,255.5 L 322.9,317.4 Z"/>
        <path class="face" fill="#b28217" d="M 353.2,418.1 L 331.5,406.1 L 319.7,400.1 L 374.7,354.1 L 353.2,418.1 Z"/>
        </g>
<g class="zone head">
        <path class="face" fill="#28949a" d="M 478.1,210.6 L 485.9,179.1 L 565.5,184.9 L 580.5,189.5 L 504.6,311.1 L 488.4,261.7 L 473.4,243.9 L 478.1,210.6 Z"/>
        <path class="face" fill="#1b394b" d="M 580.5,189.5 L 582.0,256.3 L 504.6,311.1 L 580.5,189.5 Z"/>
        <path class="face" fill="#3fb6aa" d="M 580.5,189.5 L 565.5,184.9 L 547.1,144.6 L 652.2,178.6 L 596.8,189.8 L 580.5,189.5 Z"/>
        <path class="face" fill="#39b79e" d="M 436.4,308.7 L 408.8,255.5 L 473.4,243.9 L 436.4,308.7 Z"/>
        <path class="face" fill="#377e7b" d="M 775.1,159.7 L 700.0,170.0 L 652.2,178.6 L 547.1,144.6 L 652.2,162.0 L 775.1,159.7 Z"/>
        <path class="face" fill="#16573b" d="M 582.0,256.3 L 580.5,189.5 L 596.8,189.8 L 630.7,199.0 L 582.0,256.3 Z"/>
        <path class="face" fill="#108067" d="M 565.5,184.9 L 485.9,179.1 L 547.1,144.6 L 565.5,184.9 Z"/>
        <path class="face" fill="#154c4f" d="M 700.0,170.0 L 775.1,159.7 L 630.7,199.0 L 652.2,178.6 L 700.0,170.0 Z"/>
        <path class="face" fill="#27a26f" d="M 473.4,243.9 L 488.4,261.7 L 436.4,308.7 L 473.4,243.9 Z"/>
        <path class="face" fill="#213f46" d="M 630.7,199.0 L 596.8,189.8 L 652.2,178.6 L 630.7,199.0 Z"/>
        </g></g><g class="dots"><circle class="dot" cx="809.0" cy="153.8" r="5" style="--d:0.000s"/>
<circle class="dot" cx="775.1" cy="159.7" r="5" style="--d:0.008s"/>
<circle class="dot" cx="700.0" cy="170.0" r="5" style="--d:0.016s"/>
<circle class="dot" cx="652.2" cy="162.0" r="5" style="--d:0.024s"/>
<circle class="dot" cx="652.2" cy="178.6" r="5" style="--d:0.033s"/>
<circle class="dot" cx="630.7" cy="199.0" r="5" style="--d:0.041s"/>
<circle class="dot" cx="547.1" cy="144.6" r="5" style="--d:0.049s"/>
<circle class="dot" cx="596.8" cy="189.8" r="5" style="--d:0.057s"/>
<circle class="dot" cx="580.5" cy="189.5" r="5" style="--d:0.065s"/>
<circle class="dot" cx="565.5" cy="184.9" r="5" style="--d:0.073s"/>
<circle class="dot" cx="485.9" cy="179.1" r="5" style="--d:0.082s"/>
<circle class="dot" cx="582.0" cy="256.3" r="5" style="--d:0.090s"/>
<circle class="dot" cx="478.1" cy="210.6" r="5" style="--d:0.098s"/>
<circle class="dot" cx="473.4" cy="243.9" r="5" style="--d:0.106s"/>
<circle class="dot" cx="424.0" cy="212.4" r="5" style="--d:0.114s"/>
<circle class="dot" cx="488.4" cy="261.7" r="5" style="--d:0.122s"/>
<circle class="dot" cx="188.4" cy="65.5" r="5" style="--d:0.131s"/>
<circle class="dot" cx="286.8" cy="155.0" r="5" style="--d:0.139s"/>
<circle class="dot" cx="564.2" cy="346.5" r="5" style="--d:0.147s"/>
<circle class="dot" cx="504.6" cy="311.1" r="5" style="--d:0.155s"/>
<circle class="dot" cx="408.8" cy="255.5" r="5" style="--d:0.163s"/>
<circle class="dot" cx="195.2" cy="121.3" r="5" style="--d:0.171s"/>
<circle class="dot" cx="269.5" cy="194.3" r="5" style="--d:0.180s"/>
<circle class="dot" cx="436.4" cy="308.7" r="5" style="--d:0.188s"/>
<circle class="dot" cx="341.9" cy="259.9" r="5" style="--d:0.196s"/>
<circle class="dot" cx="222.4" cy="186.9" r="5" style="--d:0.204s"/>
<circle class="dot" cx="444.4" cy="342.7" r="5" style="--d:0.212s"/>
<circle class="dot" cx="526.9" cy="415.4" r="5" style="--d:0.220s"/>
<circle class="dot" cx="278.7" cy="256.0" r="5" style="--d:0.229s"/>
<circle class="dot" cx="322.9" cy="317.4" r="5" style="--d:0.237s"/>
<circle class="dot" cx="374.7" cy="354.1" r="5" style="--d:0.245s"/>
<circle class="dot" cx="226.7" cy="307.3" r="5" style="--d:0.253s"/>
<circle class="dot" cx="173.1" cy="281.0" r="5" style="--d:0.261s"/>
<circle class="dot" cx="452.1" cy="475.6" r="5" style="--d:0.269s"/>
<circle class="dot" cx="353.2" cy="418.1" r="5" style="--d:0.278s"/>
<circle class="dot" cx="331.5" cy="406.1" r="5" style="--d:0.286s"/>
<circle class="dot" cx="319.7" cy="400.1" r="5" style="--d:0.294s"/>
<circle class="dot" cx="235.0" cy="379.5" r="5" style="--d:0.302s"/>
<circle class="dot" cx="134.6" cy="343.3" r="5" style="--d:0.310s"/>
<circle class="dot" cx="392.9" cy="519.6" r="5" style="--d:0.318s"/>
<circle class="dot" cx="5.2" cy="263.1" r="5" style="--d:0.327s"/>
<circle class="dot" cx="487.4" cy="585.9" r="5" style="--d:0.335s"/>
<circle class="dot" cx="434.0" cy="552.5" r="5" style="--d:0.343s"/>
<circle class="dot" cx="53.8" cy="301.8" r="5" style="--d:0.351s"/>
<circle class="dot" cx="364.0" cy="525.8" r="5" style="--d:0.359s"/>
<circle class="dot" cx="378.3" cy="571.4" r="5" style="--d:0.367s"/>
<circle class="dot" cx="408.5" cy="645.4" r="5" style="--d:0.376s"/>
<circle class="dot" cx="368.6" cy="721.8" r="5" style="--d:0.384s"/>
<circle class="dot" cx="368.0" cy="743.0" r="5" style="--d:0.392s"/></g></svg>
</div>
