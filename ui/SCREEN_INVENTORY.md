# SCREEN INVENTORY — from .ccbi layouts, ImGui strings and res/

Source: 643 `.ccbi` CocosBuilder layouts (`assets/ccbi_layouts/`), mod-menu strings from
`library.so`, Android res (`assets/apk_res/`).

## A. Core screens & menus (game UI)
| Screen / menu | Layout files (examples) |
|---|---|
| Account / login | `AccountLoginScreen*`, `AccountLoginButton`, `AccountInfoWidget`, `AccountConflictResolutionPopup`, `AccountLinkInfoPopup/ResultPopup` |
| User profile | `MenuUserProfileMiniPopup`, `...ScrollableContent`, profile widgets |
| Main menu / lobby | `MainMenu_*`, slot/box widgets (`MainMenu_Box`, `MainMenu_Slot`) |
| Game HUD | `8BP-Frame-*` (timer gutters), cue/power widgets, `CushionShotTutorial` |
| Daily Spin | `DailySpin_Wheel/Arrow/Lights/BigFrames/MainButton/Click/Transition` (+ skel anims) |
| Clubs | `ClubsOverview_Top/Bottom/Shines/ConnectingLine` (+ anims) |
| Cue mastery | `CueMastery-FeatureUnlock`, `CueMastery-Upgrade` (+ skel) |
| Store / offers | `AdPlacementWithBundleButton`, `v54_SubscriptionInfoCard_ConfigurablePaymentPeriods`, `v54_EliteSubscriptionContent_*`, `v54_MultiOptionSubscriptionContent_*`, `PromoConfiguration`-driven popups |
| Surprise boxes | `SurpriseBoxes_Arrow`, `BigBox_Rare/Epic/Legendary_*` particle sets, `OpenGift` |
| Inbox / notifications | `InboxSystemConfiguration`, notification layouts (Android res + remote avatars) |
| Generic dialogs | `AcceptDoubleButton`, `Popup*` family (dim + centered modal pattern) |
| Activity / tabs | `ActivityTabContent` |
| Chrono/rank | `AvatarFrameRankUp`, `Champion_Medal_1/2/3` |
| Minigame promos | `CyclePromoArrow`, `GS_*_Rings_Animation` (GameServices rings) |
| IAP | `IPlayTempleView` analytics hooks (`alertShow` key `2000090`, `alertDismiss` `2000091`, `play…` per `builddatas.json`) |

## B. Android-native UI (res/)
- Splash + launcher icons (mipmap PNG/webp, `assets/apk_res/`)
- Rich notifications: `ChallengeNotificationLayout` / `NonChallengeNotificationLayout` — RemoteViews
  with avatars (100/200 px rounded 25 px), level/name/Play-button bitmaps drawn in code
  (sizes/colors in `ui/UI_DESIGN_SYSTEM.md` §2.3)
- Facebook smart-login dialog (`com_smart_login_code`), FaceTec liveness screens, BidMachine privacy
  sheet (`bm_privacy_*`)

## C. Mod menu overlay (ImGui, `library.so`)
| Window / control | Strings |
|---|---|
| Login gate | `Premium 8 Ball Pool Assistant`, `LOGIN (paste key from clipboard)`, `Paste your license key then tap LOGIN`, `To get a key, contact Telegram @bum7md8` |
| License info | `License Information`, `License Key:`, `Device HWID: `, `Tier %s`, `EXPIRY TIME:`, `Developer: @bum7md8`, `SELLER: `, `Telegram: `, `Server: watan-keys.cu.ma`, `Logout` |
| Aimbot panel | `AutoAim Enhanced`, `fAIM_*` sliders, `cAIM_SnapLine*` colors, `iLineStyle` (SOLID/STRIPE) |
| ESP panel | `ESP::STATES`, `Draw Pockets`, `cESP_*` colors, `fESP_*` sliders, `iXray` |
| AutoPlay panel | `Auto Play`, `Enable Auto Play`, `Delay between shots`, `fShotPower`, `fSpeed`, `Approve the shot?`, `Auto-accept in` |
| Auto Queue | `Auto Queue`, `Enable Auto Queue`, `Queue selection mode`, `StartLastMatch`, bet = 50% coins |
| Tools | `Tools`, `Fonts`, `Language`, `Notifications`, `Log To File`, `Table`, `Default Depth`, `Draw Shot State` |
| Chrome | `##MainMenuBar`, `##Menu_%02d`, `##ContextMenu`, `#CLOSE`, modals, color pickers, FPS overlay |

## D. Localization
17 `.lproj` string tables (`assets/localization/`): ar, de, eng, es, fr, hi, id, it, ja, ko, kor,
pt, pt-BR, pt-PT, ru, tr, vi.
