</table>
        <!--[if (gte mso 9)|(IE)]>
        </td>
        </tr>
        </table>
        <![endif]-->
      </td>
    </tr>
    <!-- end copy block -->

    <!-- start footer -->
    <tr>
      <td align="center" bgcolor="#f1f1f1" style="padding: 24px;">
        <!--[if (gte mso 9)|(IE)]>
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="600">
        <tr>
        <td align="center" valign="top" width="600">
        <![endif]-->
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">

          <!-- start unsubscribe -->
          <tr>
            <td align="center" bgcolor="#f1f1f1" style="padding: 12px 24px; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; font-size: 14px; line-height: 20px; color: #666;">
                <?php if ( !is_plugin_active( 'wp-client-reports-pro/wp_client_reports_pro.php' ) ) : ?>
                    <p style="margin: 0 0 10px;">
                        <?php printf( __( 'Report created with %1$sWP Client Reports%2$s.', 'wp-client-reports' ), '<a href="https://switchwp.com/plugins/wp-client-reports/?utm_source=email&utm_medium=report&utm_campaign=footer" target="_blank">', '</a>' ); ?>
                    </p>
                <?php endif; ?>
                <p style="margin: 0 0 10px;">
                    <?php
                        $email_footer = get_option( 'wp_client_reports_email_footer', null );
                        if (!$email_footer) {
                            $email_footer = sprintf( __( 'This email was sent by an administrator at %s.', 'wp-client-reports' ), '<a href="' . site_url() . '">' . get_bloginfo('name') . '</a>' );
                        }
                        $allowed_html = ['strong' => [], 'em' => [], 'b' => [], 'i' => [], 'a' => ['href' => [] ] ];
                        if ($email_footer) {
                            $email_footer = wpautop($email_footer);
                            $email_footer = stripslashes(wp_kses($email_footer, $allowed_html));
                        }
                    ?>
                    <?php echo $email_footer; ?>
                </p>
            </td>
          </tr>
          <!-- end unsubscribe -->

        </table>
        <!--[if (gte mso 9)|(IE)]>
        </td>
        </tr>
        </table>
        <![endif]-->
      </td>
    </tr>
    <!-- end footer -->

  </table>
  <!-- end body -->

</body>
</html>
